<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorDay;
use App\Models\Mood;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use App\Services\Bingo;
use App\Services\CommitStreak;
use App\Services\Fortune;
use App\Services\MoodInsights;
use App\Services\Quiz;
use App\Services\UptimeWall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class CornerTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $title, string $body): Page
    {
        return Page::create(['space_id' => Space::firstOrCreate(['name' => 'Ops'])->id, 'title' => $title, 'body' => $body]);
    }

    public function test_the_dashboard_shows_the_corner_with_streak_fortune_and_mood_buttons(): void
    {
        Process::fake(['*' => Process::result((string) now()->timestamp."\n".now()->subDay()->timestamp."\n")]);
        Cache::flush();
        $this->actingAs(User::factory()->create());
        $this->page('Deploys', 'We always deploy the staging site before the production site goes live.');

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Commit streak')->assertSee('🔥 2')->assertSee('Committed today.')
            ->assertSee('We always deploy the staging site before the production site goes live.')
            ->assertSee('How is today?')->assertSee(route('quiz'))->assertSee(route('bingo'));
    }

    public function test_a_streak_survives_a_quiet_today_until_the_evening(): void
    {
        Process::fake(['*' => Process::result(now()->subDay()->timestamp."\n".now()->subDays(2)->timestamp."\n")]);
        Cache::flush();

        $streak = app(CommitStreak::class)->current();

        $this->assertSame(2, $streak['days']);
        $this->assertFalse($streak['committed_today']);
    }

    public function test_the_streak_nudge_only_goes_out_when_a_streak_is_at_stake(): void
    {
        config(['services.telegram.token' => 't', 'services.telegram.chat_id' => '1']);
        Http::fake();
        Process::fake(['*' => Process::result(now()->subDay()->timestamp."\n")]);
        Cache::flush();

        $this->artisan('lore:streak-nudge')->assertSuccessful();
        Http::assertSentCount(1);

        Process::fake(['*' => Process::result(now()->timestamp."\n")]);
        Cache::flush();
        $this->artisan('lore:streak-nudge')->assertSuccessful();
        Http::assertSentCount(1);
    }

    public function test_the_fortune_is_a_real_sentence_from_a_page_and_stays_the_same_all_day(): void
    {
        Cache::flush();
        $this->page('Notes', "# Heading\n\n- [ ] short\n\nThe backup job runs every night at three and keeps fourteen copies.\n");

        $first = app(Fortune::class)->today();

        $this->assertSame('The backup job runs every night at three and keeps fourteen copies.', $first['line']);
        $this->assertSame($first, app(Fortune::class)->today());
    }

    public function test_the_quiz_blanks_out_a_term_and_checks_the_answer_ignoring_case(): void
    {
        $this->actingAs(User::factory()->create());
        $page = $this->page('Server', 'The web server listens on `Port8080` for every request.');

        $this->get(route('quiz'))->assertOk()->assertSee('The web server listens on _____ for every request.');
        $this->post(route('quiz.answer'), ['answer' => ' port8080 '])->assertOk()->assertSee('Correct!');

        $this->get(route('quiz'));
        $this->post(route('quiz.answer'), ['answer' => 'nope'])->assertOk()->assertSee('Not quite.')->assertSee('Port8080');
        $this->assertTrue(app(Quiz::class)->isCorrect('A  B', 'a b'));
        $this->assertFalse(app(Quiz::class)->isCorrect('', ''));
        $this->assertNotNull($page);
    }

    public function test_the_quiz_has_nothing_to_ask_without_suitable_notes(): void
    {
        $this->actingAs(User::factory()->create());
        $this->page('Plain', 'short');

        $this->get(route('quiz'))->assertOk()->assertSee('No question yet');
    }

    public function test_the_bingo_card_is_the_same_all_week_and_lights_visited_countries(): void
    {
        $bingo = app(Bingo::class);
        $card = $bingo->card([]);
        $codes = collect($card['rows'])->flatten(1)->pluck('code');
        $visited = $codes->first(fn ($code) => $code !== 'FREE');

        $lit = $bingo->card([$visited => 3]);

        $this->assertSame(25, $codes->count());
        $this->assertSame($card['rows'], $bingo->card([], now()->startOfWeek()->addDays(2))['rows']);
        $this->assertSame(0, $card['lit']);
        $this->assertSame(1, $lit['lit']);
        $this->actingAs(User::factory()->create())->get(route('bingo'))->assertOk()->assertSee('Visitor bingo');
    }

    public function test_the_mood_is_saved_once_a_day_and_compared_with_rough_days(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('mood.store'), ['level' => 2]);
        $this->post(route('mood.store'), ['level' => 4]);
        $this->post(route('mood.store'), ['level' => 9])->assertSessionHasErrors('level');

        $this->assertSame(1, Mood::count());
        $this->assertSame(4, Mood::firstOrFail()->level);

        Mood::query()->delete();
        foreach ([1, 2, 3] as $ago) {
            Mood::create(['day' => today()->subDays($ago), 'level' => 1]);
            Incident::factory()->create(['monitor_id' => null, 'started_at' => today()->subDays($ago)->setHour(10)]);
        }
        foreach ([4, 5, 6] as $ago) {
            Mood::create(['day' => today()->subDays($ago), 'level' => 5]);
        }

        $insight = app(MoodInsights::class)->insight(app(MoodInsights::class)->days());

        $this->assertStringContainsString('4 lower', $insight);
    }

    public function test_the_graveyard_lists_forgotten_pages_and_pages_can_be_revived_or_buried(): void
    {
        $this->actingAs(User::factory()->create());
        $old = $this->page('Forgotten', 'old');
        $fresh = $this->page('Fresh', 'new');
        Page::withoutTimestamps(fn () => $old->forceFill(['updated_at' => now()->subYear()])->save());

        $this->get(route('graveyard'))->assertOk()->assertViewHas('pages', fn ($pages) => $pages->pluck('title')->all() === ['Forgotten']);

        $this->post(route('graveyard.revive', $old))->assertRedirect(route('graveyard'));
        $this->get(route('graveyard'))->assertViewHas('pages', fn ($pages) => $pages->isEmpty());

        Page::withoutTimestamps(fn () => $old->forceFill(['updated_at' => now()->subYear(), 'viewed_at' => null])->save());
        $this->delete(route('graveyard.bury', $old))->assertRedirect(route('graveyard'));
        $this->assertModelMissing($old);
        $this->assertModelExists($fresh);
    }

    public function test_surprise_me_opens_a_page_and_falls_back_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('surprise'))->assertRedirect(route('dashboard'));

        $page = $this->page('Only one', 'x');
        $this->get(route('surprise'))->assertRedirect(route('pages.show', $page));
    }

    public function test_the_arcade_page_loads_with_a_target(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('arcade'))->assertOk()->assertSee('data-target="10"', false);
    }

    public function test_the_uptime_rollup_feeds_the_wall_and_the_wall_renders_on_the_site_page(): void
    {
        $this->actingAs(User::factory()->create());
        $monitor = Monitor::factory()->create();
        foreach ([true, true, true, false] as $up) {
            MonitorCheck::create(['monitor_id' => $monitor->id, 'is_up' => $up, 'response_ms' => 50, 'checked_at' => now()->subHour()]);
        }

        $this->artisan('monitors:rollup')->assertSuccessful();

        $day = MonitorDay::firstWhere('day', now()->subHour()->toDateString());
        $this->assertSame([4, 3], [$day->checks, $day->up_checks]);
        $wall = app(UptimeWall::class)->for($monitor);
        $this->assertCount(UptimeWall::WEEKS, $wall);
        $this->assertSame(75.0, collect($wall)->flatten(1)->filter()->firstWhere('day', $day->day->toDateString())['percent']);
        $this->assertSame('bg-red-500', UptimeWall::colour(75.0));
        $this->get(route('monitors.show', $monitor))->assertOk()->assertSee('Uptime wall');
    }

    public function test_site_pets_reflect_the_state_of_the_site(): void
    {
        $this->actingAs(User::factory()->create());
        Monitor::factory()->create(['name' => 'happy-one', 'is_up' => true, 'status_changed_at' => now()->subDays(40)]);
        Monitor::factory()->create(['name' => 'sick-one', 'is_up' => false, 'status_changed_at' => now()]);

        $this->get(route('monitors.index'))->assertOk()->assertSee('data-pet="happy"', false)->assertSee('data-pet="sick"', false)->assertSee('happy and has been up for a month');
    }

    public function test_the_deploy_endpoint_reports_the_latest_finished_deploy_for_the_celebration(): void
    {
        $this->actingAs(User::factory()->create());
        Deployment::factory()->create(['status' => Deployment::SUCCEEDED, 'smoke_status' => Deployment::SMOKE_FAILED]);

        $this->getJson(route('deploy.active'))->assertJsonPath('latest.ok', false);
    }
}
