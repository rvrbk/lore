<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\Monitor;
use App\Models\User;
use App\Services\SearchConsole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchConsoleTest extends TestCase
{
    use RefreshDatabase;

    private string $keyFile;

    protected function setUp(): void
    {
        parent::setUp();
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);
        $this->keyFile = tempnam(sys_get_temp_dir(), 'gsc');
        file_put_contents($this->keyFile, json_encode(['client_email' => 'gsc@example.iam.gserviceaccount.com', 'private_key' => $pem]));
        config(['services.google_analytics' => ['credentials' => $this->keyFile]]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        @unlink($this->keyFile);
        parent::tearDown();
    }

    /**
     * @param  array<int, string>  $properties
     */
    private function fakeGoogle(array $properties): void
    {
        $today = now()->toDateString();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => array_map(fn (string $url) => ['siteUrl' => $url, 'permissionLevel' => 'siteOwner'], $properties)]),
            '*/searchAnalytics/query' => fn ($request) => Http::response($request['dimensions'][0] === 'date'
                ? ['rows' => [['keys' => [$today], 'clicks' => 4, 'impressions' => 100, 'ctr' => 0.04, 'position' => 6.5]]]
                : ['rows' => [['keys' => ['pointy booking'], 'clicks' => 3, 'impressions' => 40, 'ctr' => 0.075, 'position' => 3.2]]]),
            '*/sitemaps' => Http::response(['sitemap' => [['errors' => '2', 'warnings' => '0']]]),
            'searchconsole.googleapis.com/*' => Http::response(['inspectionResult' => ['indexStatusResult' => ['verdict' => 'PASS', 'coverageState' => 'Submitted and indexed']]]),
        ]);
    }

    public function test_a_site_is_matched_to_a_domain_property_and_reports_its_search_numbers(): void
    {
        $this->fakeGoogle(['sc-domain:verbeek.ug', 'sc-domain:other.com']);
        $monitor = Monitor::factory()->create(['url' => 'https://verbeek.ug/']);

        $report = app(SearchConsole::class)->report($monitor);

        $this->assertSame('sc-domain:verbeek.ug', $report['property']);
        $this->assertSame(4, $report['clicks']);
        $this->assertSame(100, $report['impressions']);
        $this->assertSame(4.0, $report['ctr']);
        $this->assertSame(6.5, $report['position']);
        $this->assertSame(2, $report['sitemap_problems']);
        $this->assertSame(['verdict' => 'PASS', 'state' => 'Submitted and indexed'], $report['home']);
        $this->assertSame('pointy booking', $report['queries'][0]['query']);
        $this->assertSame(SearchConsole::DAYS, count($report['daily_clicks']));
    }

    public function test_a_subdomain_site_only_counts_its_own_pages_in_a_domain_property(): void
    {
        $this->fakeGoogle(['sc-domain:verbeek.ug']);
        $monitor = Monitor::factory()->create(['url' => 'https://collective-canvas.verbeek.ug/']);

        app(SearchConsole::class)->report($monitor);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/searchAnalytics/query') && $request['dimensionFilterGroups'][0]['filters'][0]['expression'] === '://collective-canvas.verbeek.ug/');
    }

    public function test_the_longest_url_prefix_property_wins_and_unknown_sites_have_no_report(): void
    {
        $this->fakeGoogle(['https://shop.test/', 'https://shop.test/blog/']);
        $blog = Monitor::factory()->create(['url' => 'https://shop.test/blog/']);
        $stranger = Monitor::factory()->create(['url' => 'https://nobody.test/']);

        $this->assertSame('https://shop.test/blog/', app(SearchConsole::class)->propertyFor($blog));
        $this->assertNull(app(SearchConsole::class)->report($stranger));
    }

    public function test_nothing_is_cached_when_google_does_not_answer(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => [['siteUrl' => 'sc-domain:verbeek.ug']]]),
            '*' => Http::response('denied', 403),
        ]);
        $monitor = Monitor::factory()->create(['url' => 'https://verbeek.ug/']);

        $this->assertNull(app(SearchConsole::class)->report($monitor));
        $this->assertNull(Cache::get('search-console.v1.report.'.$monitor->id));
    }

    public function test_the_analytics_page_shows_search_numbers_with_deploy_markers_and_the_site_page_does_not(): void
    {
        $this->fakeGoogle(['sc-domain:verbeek.ug']);
        $this->actingAs(User::factory()->create());
        $monitor = Monitor::factory()->create(['name' => 'verbeek.ug', 'url' => 'https://verbeek.ug/', 'deploy_path' => '/var/www/verbeek-ug']);
        Deployment::factory()->create(['path' => '/var/www/verbeek-ug', 'finished_at' => now()->subDays(5)]);

        $this->get(route('analytics.index'))->assertOk()
            ->assertSee('Google Search')->assertSee('pointy booking')->assertSee('Submitted and indexed')->assertSee('Sitemap errors')->assertSee('Dashed lines mark deploys');
        $this->get(route('monitors.show', $monitor))->assertOk()->assertDontSee('Google Search');
    }

    public function test_the_refresh_command_rebuilds_the_cache(): void
    {
        $this->fakeGoogle(['sc-domain:verbeek.ug']);
        $monitor = Monitor::factory()->create(['url' => 'https://verbeek.ug/']);

        $this->artisan('search-console:refresh')->assertSuccessful();

        $this->assertIsArray(Cache::get('search-console.v1.report.'.$monitor->id));
    }

    public function test_a_site_can_be_kept_out_of_the_google_search_section(): void
    {
        $this->fakeGoogle(['sc-domain:verbeek.ug']);
        $this->actingAs(User::factory()->create());

        $this->post(route('analytics.store'), ['name' => 'verbeek.ug', 'url' => 'https://verbeek.ug/'])->assertRedirect();
        $this->post(route('analytics.store'), ['name' => 'lore', 'url' => 'https://lore.verbeek.ug/', 'show_in_search' => '0'])->assertRedirect();

        $this->assertTrue(Monitor::where('name', 'verbeek.ug')->value('show_in_search'), 'The box is on by default.');
        $this->assertFalse(Monitor::where('name', 'lore')->value('show_in_search'));

        $this->get(route('analytics.index'))->assertOk()->assertSee('pointy booking');
        $this->get(route('analytics.index'))->assertDontSee('Not found in Search Console, or no answer yet: lore');
        $this->artisan('search-console:refresh')->assertSuccessful();
        $this->assertNull(Cache::get('search-console.v1.report.'.Monitor::where('name', 'lore')->value('id')));

        $lore = Monitor::where('name', 'lore')->first();
        $this->put(route('monitors.update', $lore), ['name' => 'lore', 'url' => 'https://lore.verbeek.ug/', 'show_in_search' => '1'])->assertRedirect();
        $this->assertTrue($lore->fresh()->show_in_search);
    }
}
