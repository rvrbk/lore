<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Services\DeployImpact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeployImpactTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deploy_is_marked_on_its_day_and_visitors_before_and_after_are_compared(): void
    {
        Monitor::factory()->create(['name' => 'shop', 'deploy_path' => '/var/www/shop']);
        Deployment::factory()->create(['path' => '/var/www/shop', 'finished_at' => now()->subDays(3)]);

        $result = app(DeployImpact::class)->forTraffic('shop', [100, 100, 100, 100, 50, 50, 50]);

        $this->assertSame([3], $result['markers']);
        $this->assertSame(100.0, $result['change']['before']);
        $this->assertSame(50.0, $result['change']['after']);
        $this->assertSame(-50, $result['change']['percent']);
    }

    public function test_failed_deploys_and_other_sites_are_ignored(): void
    {
        Monitor::factory()->create(['name' => 'shop', 'deploy_path' => '/var/www/shop']);
        Deployment::factory()->create(['path' => '/var/www/shop', 'status' => 'failed']);
        Deployment::factory()->create(['path' => '/var/www/other']);

        $result = app(DeployImpact::class)->forTraffic('shop', [1, 1, 1, 1, 1, 1, 1]);

        $this->assertSame([], $result['markers']);
        $this->assertNull($result['change']);
    }

    public function test_response_time_is_compared_across_the_latest_deploy(): void
    {
        $monitor = Monitor::factory()->create(['deploy_path' => '/var/www/app']);
        $deploy = Deployment::factory()->create(['path' => '/var/www/app', 'finished_at' => now()->subHours(2)]);
        foreach ([[4, 100], [3, 100], [1, 300], [0, 300]] as [$hoursAgo, $ms]) {
            MonitorCheck::create(['monitor_id' => $monitor->id, 'is_up' => true, 'response_ms' => $ms, 'checked_at' => now()->subHours($hoursAgo)]);
        }

        $result = app(DeployImpact::class)->forResponse($monitor->checks()->orderBy('checked_at')->get(), collect([$deploy]));

        $this->assertSame([2], $result['markers']);
        $this->assertSame(['before' => 100, 'after' => 300, 'percent' => 200], $result['change']);
    }
}
