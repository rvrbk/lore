<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Page;
use App\Models\Space;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class IncidentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Process::fake();
        config(['services.telegram.token' => 'token', 'services.telegram.chat_id' => '1']);
        $this->actingAs(User::factory()->create());
    }

    public function test_an_outage_opens_one_incident_with_a_board_card_and_closes_when_the_site_recovers(): void
    {
        $monitor = Monitor::factory()->create(['name' => 'Shop', 'url' => 'https://shop.test/up']);

        Http::fake(['https://shop.test/*' => Http::response('boom', 502), 'https://api.telegram.org/*' => Http::response(['ok' => true])]);
        $this->artisan('monitors:check');
        $this->assertSame(0, Incident::count(), 'A single failed check stays quiet.');
        $this->artisan('monitors:check');
        $this->artisan('monitors:check');

        $incident = Incident::sole();
        $this->assertSame(Incident::DOWN, $incident->kind);
        $this->assertTrue($incident->isOpen());
        $card = Task::findOrFail($incident->task_id);
        $this->assertSame('high', $card->priority);
        $this->assertSame('todo', $card->status);
        $this->assertContains('shop', $card->tags->pluck('name')->all());
        $this->get(route('dashboard'))->assertSee('1 open incident');

        Http::swap(new Factory);
        Http::fake(['https://shop.test/*' => Http::response('ok', 200), 'https://api.telegram.org/*' => Http::response(['ok' => true])]);
        $this->artisan('monitors:check');

        $this->assertFalse($incident->fresh()->isOpen());
        $this->assertStringContainsString('Resolved after', $card->fresh()->description);
        $this->assertSame($monitor->id, $incident->monitor_id);
    }

    public function test_an_outage_right_after_a_deploy_points_at_that_deploy(): void
    {
        $monitor = Monitor::factory()->create(['deploy_path' => '/var/www/shop']);
        $deployment = Deployment::factory()->create(['path' => '/var/www/shop', 'finished_at' => now()->subMinutes(5)]);
        Deployment::factory()->create(['path' => '/var/www/shop', 'finished_at' => now()->subHours(3)]);

        $monitor->forceFill(['is_up' => false, 'consecutive_failures' => 1])->save();
        Http::fake(['*' => Http::response('boom', 500)]);
        Monitor::check($monitor->newCollection([$monitor]));

        $this->assertSame($deployment->id, Incident::sole()->deployment_id);
    }

    public function test_a_post_mortem_page_is_created_once_from_the_incident(): void
    {
        $space = Space::create(['name' => 'Ops']);
        $incident = Incident::factory()->resolved()->create(['title' => 'Shop is down', 'notes' => 'Disk was full']);

        $this->post(route('incidents.post-mortem', $incident))->assertRedirect();
        $this->post(route('incidents.post-mortem', $incident));

        $page = Page::sole();
        $this->assertSame($space->id, $page->space_id);
        $this->assertSame($page->id, $incident->fresh()->page_id);
        $this->assertStringContainsString('Disk was full', $page->body);
        $this->assertStringContainsString('## Root cause', $page->body);
    }

    public function test_notes_can_be_saved_and_the_page_is_login_protected(): void
    {
        $incident = Incident::factory()->create();

        $this->put(route('incidents.update', $incident), ['notes' => 'Restarted php-fpm'])->assertRedirect();
        $this->assertSame('Restarted php-fpm', $incident->fresh()->notes);
        $this->get(route('incidents.index'))->assertOk()->assertSee($incident->title);

        auth()->logout();
        $this->get(route('incidents.index'))->assertRedirect(route('login'));
    }
}
