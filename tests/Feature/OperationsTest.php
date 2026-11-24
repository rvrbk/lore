<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Page;
use App\Models\Reminder;
use App\Models\ServerMetric;
use App\Models\Space;
use App\Models\Task;
use App\Models\User;
use App\Services\Deployer;
use App\Services\ExpiryWatcher;
use App\Services\ServerHealth;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $telegramUser;

    protected function setUp(): void
    {
        parent::setUp();
        Process::fake();
        config(['services.deploy.smoke_retry_seconds' => 0, 'services.telegram.token' => 'token']);
        $this->telegramUser = User::factory()->create(['telegram_chat_id' => '1', 'digest_subscribed' => true]);
    }

    public function test_a_deploy_whose_site_does_not_answer_opens_an_incident_and_a_later_good_deploy_closes_it(): void
    {
        $monitor = Monitor::factory()->create(['name' => 'Shop', 'url' => 'https://shop.test/up', 'deploy_path' => '/var/www/shop']);
        $monitor->subscribers()->attach($this->telegramUser);
        $bad = Deployment::factory()->create(['path' => '/var/www/shop', 'site' => 'Shop']);
        Http::fake(['https://shop.test/*' => Http::response('boom', 500), 'https://api.telegram.org/*' => Http::response(['ok' => true])]);

        app(Deployer::class)->smokeCheck($bad);

        $this->assertSame('failed', $bad->fresh()->smoke_status);
        $incident = Incident::sole();
        $this->assertSame(Incident::SMOKE_FAILED, $incident->kind);
        $this->assertSame($bad->id, $incident->deployment_id);
        $this->assertSame(1, Task::count());
        Http::assertSent(fn ($request) => str_contains($request->url(), 'telegram') && str_contains($request['text'], 'failed its check'));

        $good = Deployment::factory()->create(['path' => '/var/www/shop', 'site' => 'Shop']);
        Http::swap(new Factory);
        Http::fake(['https://shop.test/*' => Http::response('ok', 200)]);
        app(Deployer::class)->smokeCheck($good);

        $this->assertSame('passed', $good->fresh()->smoke_status);
        $this->assertFalse($incident->fresh()->isOpen());
        $this->assertSame($monitor->id, $incident->monitor_id);
    }

    public function test_a_deploy_of_a_folder_without_a_monitor_is_skipped(): void
    {
        Http::fake();
        $deployment = Deployment::factory()->create(['path' => '/var/www/unmonitored']);

        app(Deployer::class)->smokeCheck($deployment);

        $this->assertSame('skipped', $deployment->fresh()->smoke_status);
        Http::assertNothingSent();
    }

    public function test_domain_expiry_creates_countdown_reminders_without_flooding_and_clears_them_on_renewal(): void
    {
        Http::fake();
        $watcher = app(ExpiryWatcher::class);
        $monitor = Monitor::factory()->create(['name' => 'Shop']);
        $monitor->subscribers()->attach($this->telegramUser);

        $watcher->apply($monitor, null, CarbonImmutable::now()->addDays(60));
        $this->assertSame(3, Reminder::count());
        $this->assertSame(0, Incident::count());
        $this->assertSame(0, Reminder::due()->count());

        $watcher->apply($monitor, null, CarbonImmutable::now()->addDays(60));
        $this->assertSame(3, Reminder::count(), 'Re-checking does not duplicate reminders.');

        $watcher->apply($monitor, null, CarbonImmutable::now()->addDays(200));
        $this->assertSame(3, Reminder::count(), 'A renewed domain replaces the old countdown.');
        $this->assertTrue(Reminder::all()->every(fn (Reminder $r) => str_contains($r->title, CarbonImmutable::now()->addDays(200)->format('j M Y'))));
    }

    public function test_a_domain_found_close_to_expiry_gets_one_due_reminder_and_an_incident_that_clears_after_renewal(): void
    {
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true])]);
        $watcher = app(ExpiryWatcher::class);
        $monitor = Monitor::factory()->create(['name' => 'Shop']);
        $monitor->subscribers()->attach($this->telegramUser);

        $watcher->apply($monitor, null, CarbonImmutable::now()->addDays(5));

        $this->assertSame(1, Reminder::count());
        $this->assertSame(1, Reminder::due()->count());
        $this->assertStringContainsString('(7 days left)', Reminder::sole()->title);
        $incident = Incident::sole();
        $this->assertSame(Incident::DOMAIN_EXPIRING, $incident->kind);

        $watcher->apply($monitor, null, CarbonImmutable::now()->addDays(90));
        $this->assertFalse($incident->fresh()->isOpen());
    }

    public function test_certificates_get_no_reminders_and_only_an_incident_once_auto_renewal_has_clearly_failed(): void
    {
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true])]);
        $watcher = app(ExpiryWatcher::class);
        $monitor = Monitor::factory()->create(['name' => 'Shop']);
        Reminder::factory()->create(['title' => 'Shop: SSL certificate expires 1 Feb 2027 (30 days left)', 'due_at' => now()]);
        Reminder::factory()->create(['title' => 'Shop: domain expires 1 Feb 2027 (30 days left)', 'due_at' => now()]);

        $watcher->apply($monitor, CarbonImmutable::now()->addDays(25), null);
        $this->assertSame(0, Incident::count(), 'Certbot renews from 30 days left, so 25 is normal.');
        $this->assertSame(['Shop: domain expires 1 Feb 2027 (30 days left)'], Reminder::pluck('title')->all(), 'Old certificate countdowns are removed.');

        $watcher->apply($monitor, CarbonImmutable::now()->addDays(10), null);
        $incident = Incident::sole();
        $this->assertSame(Incident::SSL_EXPIRING, $incident->kind);
        $this->assertSame(1, Reminder::count());

        $watcher->apply($monitor, CarbonImmutable::now()->addDays(89), null);
        $this->assertFalse($incident->fresh()->isOpen());
    }

    public function test_registrable_domains_are_found_and_unlookupable_hosts_are_skipped(): void
    {
        $watcher = app(ExpiryWatcher::class);

        $this->assertSame('verbeek.ug', $watcher->registrableDomain('lore.verbeek.ug'));
        $this->assertSame('example.co.uk', $watcher->registrableDomain('www.example.co.uk'));
        $this->assertNull($watcher->registrableDomain('127.0.0.1'));
        $this->assertNull($watcher->registrableDomain('shop.test'));
    }

    public function test_domain_expiry_is_read_from_rdap(): void
    {
        Http::fake(['https://rdap.org/domain/verbeek.ug' => Http::response(['events' => [['eventAction' => 'registration', 'eventDate' => '2020-01-01T00:00:00Z'], ['eventAction' => 'expiration', 'eventDate' => '2027-03-04T00:00:00Z']]])]);

        $this->assertSame('2027-03-04', app(ExpiryWatcher::class)->domainExpiry('verbeek.ug')->format('Y-m-d'));
        Http::swap(new Factory);
        Http::fake(['https://rdap.org/*' => Http::response('', 404)]);
        $this->assertNull(app(ExpiryWatcher::class)->domainExpiry('verbeek.ug'));
    }

    public function test_a_nearly_full_or_fast_filling_disk_opens_an_incident_and_clears_when_space_is_freed(): void
    {
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true])]);
        $health = app(ServerHealth::class);

        $high = ServerMetric::create(['recorded_at' => now(), 'disk_used_percent' => 91, 'disk_free_bytes' => 5 * 1024 ** 3]);
        $health->evaluate($high);
        $incident = Incident::sole();
        $this->assertSame(Incident::DISK_FULL, $incident->kind);
        $health->evaluate($high);
        $this->assertSame(1, Incident::count(), 'A persisting problem does not open a second incident.');

        ServerMetric::query()->delete();
        $ok = ServerMetric::create(['recorded_at' => now(), 'disk_used_percent' => 50, 'disk_free_bytes' => 50 * 1024 ** 3]);
        $health->evaluate($ok);
        $this->assertFalse($incident->fresh()->isOpen());

        ServerMetric::query()->delete();
        Incident::query()->delete();
        ServerMetric::create(['recorded_at' => now()->subDays(3), 'disk_used_percent' => 60, 'disk_free_bytes' => 1]);
        $filling = ServerMetric::create(['recorded_at' => now(), 'disk_used_percent' => 75, 'disk_free_bytes' => 1]);
        $this->assertSame(5, $health->daysUntilDiskFull());
        $health->evaluate($filling);
        $this->assertStringContainsString('filling up', Incident::sole()->title);
    }

    public function test_memory_alert_needs_three_high_samples_in_a_row(): void
    {
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true])]);
        $health = app(ServerHealth::class);

        foreach ([95, 96] as $percent) {
            $health->evaluate(ServerMetric::create(['recorded_at' => now(), 'memory_used_percent' => $percent]));
        }
        $this->assertSame(0, Incident::count());

        $health->evaluate(ServerMetric::create(['recorded_at' => now(), 'memory_used_percent' => 97]));
        $this->assertSame(Incident::MEMORY_HIGH, Incident::sole()->kind);
    }

    public function test_measure_command_stores_a_sample_and_the_health_page_shows_resources(): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create());

        $this->artisan('server:measure')->assertSuccessful();

        $this->assertSame(1, ServerMetric::count());
        $this->get(route('monitors.index'))->assertOk()->assertSee('Server resources')->assertSee('Disk used');
    }

    public function test_the_digest_summarises_the_day_and_is_sent_to_telegram(): void
    {
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true])]);
        Reminder::factory()->create(['user_id' => $this->telegramUser->id, 'title' => 'Renew hosting', 'due_at' => now()->subHour()]);
        Task::createAtBottom(['title' => 'Fix checkout', 'due_on' => today()->subDay()]);
        Incident::factory()->create(['title' => 'Shop is down']);

        $this->artisan('lore:digest')->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'Renew hosting')
            && str_contains($request['text'], 'Fix checkout')
            && str_contains($request['text'], 'Shop is down'));
    }

    public function test_the_digest_does_nothing_without_telegram(): void
    {
        config(['services.telegram.token' => null]);
        Http::fake();

        $this->artisan('lore:digest')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_site_hub_lists_everything_tagged_with_the_site_name(): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create());
        $monitor = Monitor::factory()->create(['name' => 'Shop', 'deploy_path' => '/var/www/shop']);
        $space = Space::create(['name' => 'Docs']);
        $page = $space->pages()->create(['title' => 'Shop runbook', 'body' => 'x']);
        $page->syncTagNames('shop');
        $task = Task::createAtBottom(['title' => 'Fix shop checkout']);
        $task->syncTagNames('shop');
        Task::createAtBottom(['title' => 'Unrelated task']);
        Deployment::factory()->create(['path' => '/var/www/shop']);
        Incident::factory()->create(['monitor_id' => $monitor->id, 'title' => 'Shop is down']);

        $this->get(route('monitors.show', $monitor))
            ->assertOk()
            ->assertSee('Shop runbook')
            ->assertSee('Fix shop checkout')
            ->assertSee('Shop is down')
            ->assertSee('Deploys')
            ->assertDontSee('Unrelated task');
        $this->assertSame($page->id, Page::withTag('shop')->sole()->id);
    }

    public function test_a_checklist_template_can_put_its_tasks_on_the_board(): void
    {
        $this->actingAs(User::factory()->create());
        $space = Space::create(['name' => 'Work']);
        $count = count(Page::TEMPLATES['launch-site']['tasks']);

        $this->get(route('pages.create', ['space' => $space, 'template' => 'launch-site']))->assertOk()->assertSee("Also add these {$count} tasks");

        $this->post(route('pages.store', $space), ['title' => 'Launch: Shop', 'body' => 'b', 'tags' => 'shop', 'template' => 'launch-site', 'spawn_tasks' => '1'])->assertRedirect();

        $page = Page::sole();
        $this->assertSame($count, Task::where('page_id', $page->id)->count());
        $this->assertSame($count, Task::withTag('shop')->count());
        $this->assertTrue(Task::where('page_id', $page->id)->whereDate('due_on', today())->exists());
    }

    public function test_a_template_without_the_checkbox_creates_no_tasks(): void
    {
        $this->actingAs(User::factory()->create());
        $space = Space::create(['name' => 'Work']);

        $this->post(route('pages.store', $space), ['title' => 'Launch: Shop', 'body' => 'b', 'template' => 'launch-site'])->assertRedirect();

        $this->assertSame(0, Task::count());
    }
}
