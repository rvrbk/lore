<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\User;
use App\Services\ErrorLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorLogTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/lore-errorlog-'.uniqid();
        mkdir($this->path.'/storage/logs', 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->path.'/storage/logs/*') ?: []);
        @rmdir($this->path.'/storage/logs');
        @rmdir($this->path.'/storage');
        @rmdir($this->path);
        parent::tearDown();
    }

    private function writeLog(): void
    {
        $recent = now()->subHours(2)->format('Y-m-d H:i:s');
        $older = now()->subDays(2)->format('Y-m-d H:i:s');
        file_put_contents($this->path.'/storage/logs/laravel.log', implode("\n", [
            "[{$older}] production.ERROR: Connection refused to host 10",
            "[{$recent}] production.ERROR: Connection refused to host 11",
            '#0 /var/www/app/vendor/x.php(12): stack frame',
            "[{$recent}] production.INFO: all fine",
            "[{$recent}] production.CRITICAL: Disk full",
        ])."\n");
    }

    public function test_errors_are_counted_per_day_and_identical_messages_grouped(): void
    {
        $this->writeLog();
        $monitor = Monitor::factory()->create(['deploy_path' => $this->path]);

        $summary = app(ErrorLog::class)->forMonitor($monitor);

        $this->assertSame(3, $summary['total']);
        $this->assertSame(2, $summary['last_day']);
        $this->assertSame(1, $summary['daily'][4]);
        $this->assertCount(2, $summary['recent']);
        $this->assertContains(2, array_column($summary['recent'], 'count'));
    }

    public function test_a_site_without_a_readable_log_has_no_error_summary(): void
    {
        $this->assertNull(app(ErrorLog::class)->forMonitor(Monitor::factory()->create(['deploy_path' => $this->path])));
        $this->assertNull(app(ErrorLog::class)->forMonitor(Monitor::factory()->create(['deploy_path' => null])));
    }

    public function test_the_site_page_lists_the_logged_errors(): void
    {
        $this->writeLog();
        $monitor = Monitor::factory()->create(['deploy_path' => $this->path]);

        $this->actingAs(User::factory()->create())->get(route('monitors.show', $monitor))->assertOk()->assertSee('Disk full')->assertSee('3 in the last 7 days');
    }
}
