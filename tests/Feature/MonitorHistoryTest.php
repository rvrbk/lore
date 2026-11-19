<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class MonitorHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        Process::fake();
        config(['services.monitor_alerts.webhook' => 'https://hooks.test/alert']);
    }

    private function respondWith(int $status): void
    {
        Http::swap(new Factory);
        Http::fake(['https://hooks.test/*' => Http::response('ok'), '*' => Http::response('x', $status)]);
    }

    public function test_every_check_is_stored_and_old_ones_are_pruned(): void
    {
        $monitor = Monitor::factory()->create(['url' => 'https://app.test']);
        $monitor->checks()->create(['is_up' => true, 'checked_at' => now()->subDays(31)]);
        $this->respondWith(200);

        $this->artisan('monitors:check')->assertSuccessful();

        $this->assertSame(1, MonitorCheck::count());
        $this->assertTrue(MonitorCheck::firstOrFail()->is_up);
    }

    public function test_a_down_alert_is_sent_once_after_repeated_failures_and_a_recovery_alert_follows(): void
    {
        Monitor::factory()->create(['name' => 'canvas', 'url' => 'https://canvas.test']);
        $this->respondWith(502);

        $this->artisan('monitors:check');
        Http::assertNotSent(fn ($request) => $request->url() === 'https://hooks.test/alert');

        $this->artisan('monitors:check');
        $this->artisan('monitors:check');
        Http::assertSentCount(3 + 1);
        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.test/alert' && str_contains($request['text'], 'canvas is DOWN'));
        Process::assertRanTimes(fn ($process) => str_contains(implode(' ', (array) $process->command), 'notify-send'), 1);

        $this->respondWith(200);
        $this->artisan('monitors:check');
        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.test/alert' && str_contains($request['text'], 'canvas is back UP'));
        $this->assertNull(Monitor::firstOrFail()->alerted_at);
        $this->assertSame(0, Monitor::firstOrFail()->consecutive_failures);
    }

    public function test_a_single_blip_stays_quiet(): void
    {
        Monitor::factory()->create(['url' => 'https://blip.test']);
        $this->respondWith(500);
        $this->artisan('monitors:check');
        $this->respondWith(200);
        $this->artisan('monitors:check');

        Http::assertNotSent(fn ($request) => $request->url() === 'https://hooks.test/alert');
        Process::assertNothingRan();
    }

    public function test_detail_page_shows_uptime_incidents_and_dashboard_tiles_link_to_it(): void
    {
        $monitor = Monitor::factory()->create(['name' => 'agriflow', 'is_up' => true, 'checked_at' => now(), 'status_changed_at' => now()]);
        foreach ([[40, true, 120], [30, false, null], [20, false, null], [10, true, 80]] as [$minutesAgo, $up, $ms]) {
            $monitor->checks()->create(['is_up' => $up, 'response_ms' => $ms, 'checked_at' => now()->subMinutes($minutesAgo)]);
        }

        $this->assertSame(50.0, $monitor->uptimePercent(1));
        $this->get(route('monitors.show', $monitor))->assertOk()
            ->assertSee('agriflow')->assertSee('50%')->assertSee('Down since')->assertSee('Last 24 hours');
        $this->get(route('dashboard'))->assertSee(route('monitors.show', $monitor), false)->assertSee('<svg', false);
    }
}
