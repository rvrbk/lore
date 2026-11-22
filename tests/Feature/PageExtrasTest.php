<?php

namespace Tests\Feature;

use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageExtrasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_pinning_shows_a_page_in_the_sidebar_and_unpinning_removes_it(): void
    {
        $space = Space::create(['name' => 'Ops']);
        $page = $space->pages()->create(['title' => 'Handbook']);
        $this->get(route('dashboard'))->assertDontSee('Pinned');

        $this->post(route('pages.pin', $page))->assertRedirect();
        $this->get(route('dashboard'))->assertSee('Pinned')->assertSee('Handbook');

        $this->post(route('pages.pin', $page));
        $this->get(route('dashboard'))->assertDontSee('Pinned');
    }

    public function test_viewing_a_page_lists_it_under_recent_without_changing_its_updated_time(): void
    {
        $space = Space::create(['name' => 'Ops']);
        $page = $space->pages()->create(['title' => 'Handbook']);
        $page->forceFill(['updated_at' => now()->subWeek()])->saveQuietly();
        $this->get(route('dashboard'))->assertDontSee('>Recent</div>', false);

        $this->get(route('pages.show', $page))->assertOk();

        $page->refresh();
        $this->assertNotNull($page->viewed_at);
        $this->assertTrue($page->updated_at->lt(now()->subDays(6)));
        $this->get(route('dashboard'))->assertSee('>Recent</div>', false)->assertSee('Handbook');
    }

    public function test_new_pages_can_start_from_a_template_or_a_given_title(): void
    {
        $space = Space::create(['name' => 'Ops']);
        $this->travelTo('2026-03-04 10:00:00');

        $this->get(route('pages.create', [$space, 'template' => 'meeting-notes']))
            ->assertOk()->assertSee('Meeting 2026-03-04')->assertSee('## Action items')->assertDontSee('{{date}}');
        $this->get(route('pages.create', [$space, 'template' => 'runbook']))->assertSee('## Rollback');
        $this->get(route('pages.create', [$space, 'title' => 'Brand new']))->assertSee('value="Brand new"', false);
        $this->get(route('pages.create', [$space, 'template' => 'does-not-exist']))->assertOk();
    }

    public function test_a_page_weather_follows_how_long_ago_it_was_edited(): void
    {
        $space = Space::create(['name' => 'Ops']);
        $page = $space->pages()->create(['title' => 'Handbook']);

        foreach ([[0, '☀️'], [10, '🌤️'], [45, '☁️'], [120, '🌧️'], [400, '🌫️']] as [$days, $icon]) {
            $page->updated_at = now()->subDays($days);
            $this->assertSame($icon, $page->weather()['icon'], "{$days} days old");
        }
    }

    public function test_palette_lists_pages_for_the_quick_switcher(): void
    {
        $space = Space::create(['name' => 'Ops']);
        $page = $space->pages()->create(['title' => 'Handbook']);

        $this->getJson(route('palette'))->assertOk()->assertExactJson([['title' => 'Handbook', 'space' => 'Ops', 'url' => route('pages.show', $page), 'weather' => $page->weather()]]);
        $this->get(route('dashboard'))->assertSee('data-palette', false)->assertSee('New task');

        auth()->logout();
        $this->getJson(route('palette'))->assertUnauthorized();
    }
}
