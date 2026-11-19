<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class StatusPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_page_needs_no_login_and_hides_internals(): void
    {
        $monitor = Monitor::factory()->create(['name' => 'Shop', 'url' => 'https://secret-origin.test/health', 'is_up' => true, 'error' => 'HTTP 503 internal detail', 'checked_at' => now()]);
        $monitor->setPublic(true);
        $monitor->checks()->create(['is_up' => true, 'checked_at' => now()]);
        Incident::factory()->resolved()->create(['monitor_id' => $monitor->id, 'title' => 'Internal title', 'notes' => 'private notes', 'details' => 'private details']);
        Incident::factory()->create(['monitor_id' => $monitor->id, 'kind' => Incident::SSL_EXPIRING, 'title' => 'cert thing']);

        $this->get(route('status.show', $monitor->public_token))
            ->assertOk()
            ->assertSee('All systems operational')
            ->assertSee('Past outages')
            ->assertDontSee('secret-origin')
            ->assertDontSee('internal detail')
            ->assertDontSee('private notes')
            ->assertDontSee('Internal title')
            ->assertDontSee('cert thing');
    }

    public function test_unknown_or_switched_off_tokens_are_not_found(): void
    {
        $monitor = Monitor::factory()->create();
        $monitor->setPublic(true);
        $token = $monitor->public_token;

        $monitor->setPublic(false);

        $this->get(route('status.show', $token))->assertNotFound();
        $this->get(route('status.show', 'nope'))->assertNotFound();
    }

    public function test_editing_a_site_toggles_the_public_page_and_keeps_the_token_stable(): void
    {
        Process::fake();
        Http::fake();
        $this->actingAs(User::factory()->create());
        $monitor = Monitor::factory()->create();

        $payload = ['name' => $monitor->name, 'url' => $monitor->url, 'is_public' => '1'];
        $this->put(route('monitors.update', $monitor), $payload);
        $token = $monitor->fresh()->public_token;
        $this->assertNotNull($token);

        $this->put(route('monitors.update', $monitor), $payload);
        $this->assertSame($token, $monitor->fresh()->public_token);

        $this->put(route('monitors.update', $monitor), ['name' => $monitor->name, 'url' => $monitor->url]);
        $this->assertNull($monitor->fresh()->public_token);
    }
}
