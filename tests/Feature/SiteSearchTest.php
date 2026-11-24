<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Reminder;
use App\Models\Space;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function seedContent(): void
    {
        $space = Space::create(['name' => 'Ops', 'description' => 'Zebra servers']);
        $page = $space->pages()->create(['title' => 'Runbook', 'body' => 'Restart the zebra daemon']);
        $page->syncTagNames('zebra-ops');
        Task::createAtBottom(['title' => 'Feed the zebra', 'status' => 'todo', 'priority' => 'normal']);
        Reminder::factory()->create(['title' => 'Zebra check-up', 'due_at' => now()->addDay()]);
        Monitor::factory()->create(['name' => 'Zebra Shop', 'url' => 'https://zebra.test/']);
        Incident::create(['kind' => Incident::DOWN, 'title' => 'Zebra outage', 'started_at' => now()]);
    }

    public function test_quick_search_returns_matches_from_every_kind_of_content(): void
    {
        $this->seedContent();

        $types = collect($this->getJson(route('search.quick', ['q' => 'zebra']))->assertOk()->json())->pluck('type')->unique()->values()->all();

        $this->assertEqualsCanonicalizing(['Page', 'Space', 'Task', 'Reminder', 'Site', 'Incident', 'Tag'], $types);
    }

    public function test_quick_search_limits_each_kind_and_ignores_very_short_queries(): void
    {
        $space = Space::create(['name' => 'Ops']);
        foreach (range(1, 6) as $number) {
            $space->pages()->create(['title' => "Zebra {$number}"]);
        }

        $this->assertCount(3, $this->getJson(route('search.quick', ['q' => 'zebra']))->json());
        $this->getJson(route('search.quick', ['q' => 'z']))->assertOk()->assertExactJson([]);
    }

    public function test_search_page_groups_results_and_treats_wildcards_literally(): void
    {
        $this->seedContent();
        Task::createAtBottom(['title' => 'Plain task', 'status' => 'todo', 'priority' => 'normal']);

        $this->get(route('search', ['q' => 'zebra']))->assertOk()
            ->assertSee('Pages')->assertSee('Tasks')->assertSee('Reminders')->assertSee('Sites')->assertSee('Incidents')
            ->assertSee('Feed the zebra')->assertSee('Zebra outage');
        $this->get(route('search', ['q' => '%%']))->assertOk()->assertDontSee('Plain task')->assertSee('Nothing matches.');
    }
}
