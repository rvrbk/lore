<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class MonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Process::fake();
        $this->actingAs(User::factory()->create());
    }

    public function test_checking_marks_apps_up_or_down_and_tracks_when_status_changed(): void
    {
        Http::fake([
            'https://good.test/*' => Http::response('ok', 200),
            'https://auth.test/*' => Http::response('no', 401),
            'https://broken.test/*' => Http::response('boom', 502),
            'https://gone.test/*' => fn () => throw new ConnectionException('Could not resolve host'),
        ]);
        foreach (['good', 'auth', 'broken', 'gone'] as $name) {
            Monitor::create(['name' => $name, 'url' => "https://{$name}.test/up"]);
        }

        $this->artisan('monitors:check')->assertSuccessful();

        $byName = Monitor::all()->keyBy('name');
        $this->assertTrue($byName['good']->is_up);
        $this->assertSame(200, $byName['good']->status_code);
        $this->assertTrue($byName['auth']->is_up, 'A 401 means the app is alive.');
        $this->assertFalse($byName['broken']->is_up);
        $this->assertSame('HTTP 502', $byName['broken']->error);
        $this->assertFalse($byName['gone']->is_up);
        $this->assertStringContainsString('Could not resolve host', $byName['gone']->error);

        $changedAt = $byName['good']->status_changed_at;
        $this->travel(5)->minutes();
        $this->artisan('monitors:check');
        $this->assertEquals($changedAt, $byName['good']->fresh()->status_changed_at, 'Staying up keeps the original change time.');

        Http::swap(new Factory);
        Http::fake(['*' => Http::response('down', 500)]);
        $this->artisan('monitors:check');
        $this->assertFalse($byName['good']->fresh()->is_up);
        $this->assertTrue($byName['good']->fresh()->status_changed_at->gt($changedAt));
    }

    public function test_dashboard_shows_tiles_and_down_count(): void
    {
        Monitor::factory()->create(['name' => 'agriflow', 'is_up' => true, 'status_code' => 200, 'response_ms' => 87, 'checked_at' => now(), 'status_changed_at' => now()]);
        Monitor::factory()->create(['name' => 'canvas', 'is_up' => false, 'error' => 'HTTP 502', 'checked_at' => now(), 'status_changed_at' => now()]);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Server health')->assertSee('agriflow')->assertSee('87')->assertSee('HTTP 502')->assertSee('1/2 up');
    }

    public function test_apps_can_be_added_validated_and_removed(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $this->post(route('monitors.store'), ['name' => 'x', 'url' => 'ftp://nope'])->assertSessionHasErrors('url');
        $this->post(route('monitors.store'), ['name' => 'fuganda', 'url' => 'https://fuganda.test'])->assertRedirect(route('monitors.index'));

        $monitor = Monitor::firstOrFail();
        $this->assertTrue($monitor->is_up, 'A new app is checked right away.');

        $this->put(route('monitors.update', $monitor), ['name' => 'fuganda-prod', 'url' => 'https://fuganda.test'])->assertRedirect();
        $this->assertSame('fuganda-prod', $monitor->fresh()->name);

        $this->delete(route('monitors.destroy', $monitor));
        $this->assertSame(0, Monitor::count());
    }

    public function test_guests_cannot_see_monitors(): void
    {
        auth()->logout();
        $this->get(route('monitors.index'))->assertRedirect(route('login'));
    }
}
