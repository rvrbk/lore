<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class IncidentTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_timeline_lines_up_deploys_commits_and_failed_checks_around_an_incident(): void
    {
        Process::fake(['*' => Process::result('abc1234'."\t".now()->subMinutes(50)->timestamp."\tRik\tChange the checkout\n")]);
        $this->actingAs(User::factory()->create());
        $monitor = Monitor::factory()->create(['name' => 'shop', 'deploy_path' => '/var/www/shop']);
        Deployment::factory()->create(['path' => '/var/www/shop', 'finished_at' => now()->subMinutes(45)]);
        MonitorCheck::create(['monitor_id' => $monitor->id, 'is_up' => false, 'status_code' => 502, 'checked_at' => now()->subMinutes(30)]);
        MonitorCheck::create(['monitor_id' => $monitor->id, 'is_up' => true, 'response_ms' => 90, 'checked_at' => now()->subMinutes(5)]);
        $incident = Incident::factory()->create(['monitor_id' => $monitor->id, 'title' => 'shop is down', 'started_at' => now()->subMinutes(30)]);

        $response = $this->get(route('incidents.show', $incident))->assertOk();

        $response->assertSeeInOrder(['Commit abc1234 by Rik: Change the checkout', 'Deploy #', 'Incident opened: shop is down', 'First failed check (HTTP 502)', 'Check passed again']);
    }

    public function test_an_incident_without_a_site_still_shows_when_it_opened(): void
    {
        $this->actingAs(User::factory()->create());
        $incident = Incident::factory()->create(['monitor_id' => null, 'title' => 'Disk nearly full']);

        $this->get(route('incidents.show', $incident))->assertOk()->assertSee('Incident opened: Disk nearly full');
        $this->get(route('incidents.index'))->assertOk()->assertSee(route('incidents.show', $incident));
    }
}
