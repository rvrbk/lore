<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Reminder;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class LoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_pages_render_and_keep_revisions(): void
    {
        $space = Space::create(['name' => 'Ops']);
        $this->post(route('pages.store', $space), ['title' => 'Runbook', 'body' => '# Hi <script>x</script>'])->assertRedirect();
        $page = Page::firstOrFail();

        $this->get(route('pages.show', $page))->assertOk()->assertSee('<h1>Hi', false)->assertDontSee('<script>', false);

        $this->put(route('pages.update', $page), ['title' => 'Runbook', 'body' => 'v2']);
        $this->assertCount(2, $page->revisions);

        $this->post(route('pages.restore', [$page, $page->revisions->last()->id]));
        $this->assertSame('# Hi <script>x</script>', $page->fresh()->body);
    }

    public function test_page_cannot_become_its_own_descendant(): void
    {
        $space = Space::create(['name' => 'Ops']);
        $parent = $space->pages()->create(['title' => 'A']);
        $child = $space->pages()->create(['title' => 'B', 'parent_id' => $parent->id]);

        $this->put(route('pages.update', $parent), ['title' => 'A', 'parent_id' => $child->id])->assertSessionHasErrors('parent_id');
    }

    public function test_search_finds_body_text(): void
    {
        $space = Space::create(['name' => 'Ops']);
        $space->pages()->create(['title' => 'Nginx', 'body' => 'fastcgi_pass socket']);

        $this->get(route('search', ['q' => 'fastcgi']))->assertOk()->assertSee('Nginx');
    }

    public function test_recurring_reminder_rolls_forward_and_one_off_completes(): void
    {
        $weekly = Reminder::create(['title' => 'W', 'due_at' => now()->subDays(10), 'recurrence' => 'weekly']);
        $weekly->complete();
        $this->assertTrue($weekly->fresh()->due_at->isFuture());
        $this->assertNull($weekly->fresh()->completed_at);

        $once = Reminder::create(['title' => 'O', 'due_at' => now()->subHour()]);
        $once->complete();
        $this->assertNotNull($once->fresh()->completed_at);
    }

    public function test_due_reminders_are_notified_once(): void
    {
        Process::fake();
        Reminder::create(['title' => 'Due', 'due_at' => now()->subMinute()]);
        Reminder::create(['title' => 'Later', 'due_at' => now()->addDay()]);

        $this->artisan('reminders:notify')->assertSuccessful();
        $this->artisan('reminders:notify')->assertSuccessful();

        Process::assertRanTimes(fn ($p) => in_array('Due', $p->command), 1);
        $this->assertNotNull(Reminder::where('title', 'Due')->first()->notified_at);
        $this->assertNull(Reminder::where('title', 'Later')->first()->notified_at);
    }

    public function test_snooze_resets_notification(): void
    {
        $r = Reminder::create(['title' => 'S', 'due_at' => now()->subHour(), 'notified_at' => now()]);
        $this->post(route('reminders.snooze', $r), ['for' => 'tomorrow'])->assertRedirect();
        $this->assertNull($r->fresh()->notified_at);
        $this->assertTrue($r->fresh()->due_at->isFuture());
    }

    public function test_page_view_offers_inline_editing_that_saves_through_update(): void
    {
        $space = Space::create(['name' => 'Ops']);
        $page = $space->pages()->create(['title' => 'Runbook', 'body' => 'old']);

        $this->get(route('pages.show', $page))
            ->assertOk()
            ->assertSee('data-inline-editor', false)
            ->assertSee('action="'.route('pages.update', $page).'"', false);

        $this->put(route('pages.update', $page), ['title' => 'Runbook', 'body' => 'new', 'parent_id' => ''])
            ->assertRedirect(route('pages.show', $page));
        $this->assertSame('new', $page->fresh()->body);
        $this->assertCount(1, $page->revisions);
    }
}
