<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Deployment;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorDay;
use App\Models\User;
use App\Services\CommitStreak;
use App\Services\UptimeWall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class CornerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_header_shows_the_commit_streak_small_on_every_page(): void
    {
        Process::fake(['*' => Process::result((string) now()->timestamp."\n".now()->subDay()->timestamp."\n")]);
        Cache::flush();
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertOk()->assertSee('🔥 2')->assertSee('2-day commit streak')->assertSee('Committed today.');
        $this->get(route('tasks.index'))->assertOk()->assertSee('🔥 2');
    }

    public function test_the_header_shows_the_next_active_booking(): void
    {
        $this->travelTo('2026-11-23 06:00:00');
        $this->actingAs(User::factory()->create());
        $type = BookingType::factory()->create(['name' => 'Intro call', 'timezone' => 'Europe/Amsterdam']);
        Booking::factory()->for($type, 'type')->create(['guest_name' => 'Cancelled Carl', 'starts_at' => now()->addHour(), 'ends_at' => now()->addHour()->addMinutes(30), 'cancelled_at' => now()]);
        Booking::factory()->for($type, 'type')->create(['guest_name' => 'Later Lena', 'starts_at' => now()->addDays(6), 'ends_at' => now()->addDays(6)->addMinutes(30)]);
        Booking::factory()->for($type, 'type')->create(['guest_name' => 'Soon Sam', 'starts_at' => now()->addHours(3), 'ends_at' => now()->addHours(3)->addMinutes(30)]);
        Booking::factory()->for($type, 'type')->create(['guest_name' => 'Past Pat', 'starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addMinutes(30)]);

        $this->get(route('dashboard'))->assertOk()->assertSee('Soon Sam')->assertSee('in 3h')->assertDontSee('Cancelled Carl')->assertDontSee('Later Lena')->assertDontSee('Past Pat');
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
        config(['services.telegram.token' => 't']);
        User::factory()->create(['telegram_chat_id' => '1']);
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

    public function test_the_deploy_endpoint_reports_the_latest_finished_deploy_for_the_celebration(): void
    {
        $this->actingAs(User::factory()->create());
        Deployment::factory()->create(['status' => Deployment::SUCCEEDED, 'smoke_status' => Deployment::SMOKE_FAILED]);

        $this->getJson(route('deploy.active'))->assertJsonPath('latest.ok', false);
    }
}
