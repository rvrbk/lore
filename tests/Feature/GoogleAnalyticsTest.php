<?php

namespace Tests\Feature;

use App\Models\AnalyticsSite;
use App\Models\Monitor;
use App\Models\User;
use App\Services\GoogleAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_shows_seven_day_totals_per_property(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);
        $path = tempnam(sys_get_temp_dir(), 'ga');
        file_put_contents($path, json_encode(['client_email' => 'ga@example.iam.gserviceaccount.com', 'private_key' => $pem]));
        config(['services.google_analytics' => ['credentials' => $path]]);
        AnalyticsSite::create(['name' => 'Verbeek', 'property_id' => '111']);
        AnalyticsSite::create(['name' => 'Broken', 'property_id' => '222']);
        Cache::flush();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            '*properties/111:runReport' => fn ($request) => Http::response($request['dimensions'][0]['name'] === 'countryId'
                ? ['rows' => [['dimensionValues' => [['value' => 'NL']], 'metricValues' => [['value' => '9']]], ['dimensionValues' => [['value' => 'UG']], 'metricValues' => [['value' => '3']]]]]
                : [
                    'rows' => [['dimensionValues' => [['value' => now()->format('Ymd')]], 'metricValues' => [['value' => '5'], ['value' => '6'], ['value' => '7']]]],
                    'totals' => [['metricValues' => [['value' => '1234'], ['value' => '2000'], ['value' => '9876']]]],
                ]),
            '*properties/222:runReport' => Http::response('denied', 403),
            '*properties/111:runRealtimeReport' => Http::response(['rows' => [['metricValues' => [['value' => '4']]]]]),
            '*properties/222:runRealtimeReport' => Http::response(['rows' => [['metricValues' => [['value' => '3']]]]]),
        ]);
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Verbeek')->assertSee('1,234')->assertSee('9,876')
            ->assertSee('Daily users for Verbeek')->assertSee('Visitors by country')->assertSee('Netherlands: 9 users', false)->assertSee('Uganda: 3 users', false)->assertSee('Broken')->assertSee('Could not load')->assertSee('7 live now')->assertSee('Verbeek 4')->assertSee('Broken 3');

        unlink($path);
    }

    public function test_the_live_endpoint_lists_the_sites_with_active_users(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);
        $path = tempnam(sys_get_temp_dir(), 'ga');
        file_put_contents($path, json_encode(['client_email' => 'ga@example.iam.gserviceaccount.com', 'private_key' => $pem]));
        config(['services.google_analytics' => ['credentials' => $path]]);
        AnalyticsSite::create(['name' => 'Quiet', 'property_id' => '111']);
        AnalyticsSite::create(['name' => 'Busy', 'property_id' => '222']);
        Cache::flush();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            '*properties/111:runRealtimeReport' => Http::response([]),
            '*properties/222:runRealtimeReport' => Http::response(['rows' => [
                ['dimensionValues' => [['value' => 'NL']], 'metricValues' => [['value' => '3']]],
                ['dimensionValues' => [['value' => 'ug']], 'metricValues' => [['value' => '2']]],
                ['dimensionValues' => [['value' => '(not set)']], 'metricValues' => [['value' => '1']]],
            ]]),
        ]);

        $this->get(route('analytics.live'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->getJson(route('analytics.live'))->assertOk()->assertExactJson(['total' => 6, 'sites' => [['name' => 'Busy', 'users' => 6]], 'countries' => ['NL' => 3, 'UG' => 2]]);

        unlink($path);
    }

    public function test_the_live_endpoint_reports_null_when_not_configured(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson(route('analytics.live'))->assertOk()->assertExactJson(['total' => null, 'sites' => [], 'countries' => []]);
    }

    public function test_a_failed_fetch_is_not_cached_so_the_next_request_recovers(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);
        $path = tempnam(sys_get_temp_dir(), 'ga');
        file_put_contents($path, json_encode(['client_email' => 'ga@example.iam.gserviceaccount.com', 'private_key' => $pem]));
        config(['services.google_analytics' => ['credentials' => $path]]);
        AnalyticsSite::create(['name' => 'Verbeek', 'property_id' => '111']);
        Cache::flush();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::sequence()->push('down', 500)->push(['access_token' => 'tok']),
            '*runReport' => Http::response(['totals' => [['metricValues' => [['value' => '8'], ['value' => '9'], ['value' => '10']]]]]),
        ]);
        $this->assertNull(app(GoogleAnalytics::class)->summary());
        $this->assertSame(8, app(GoogleAnalytics::class)->summary()[0]['users']);

        unlink($path);
    }

    public function test_it_stays_hidden_when_not_configured(): void
    {
        $this->assertNull(app(GoogleAnalytics::class)->summary());
        $this->actingAs(User::factory()->create());
        $this->get(route('dashboard'))->assertOk()->assertDontSee('last 7 days');
    }

    public function test_properties_can_be_added_and_removed_on_the_analytics_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('analytics.index'))->assertOk()->assertSee('No properties yet');

        Http::fake();
        $this->post(route('analytics.store'), ['name' => 'Verbeek', 'url' => 'https://verbeek.ug', 'property_id' => '547074352'])->assertRedirect(route('analytics.index'));
        $site = AnalyticsSite::sole();
        $this->assertSame('https://verbeek.ug', Monitor::sole()->url);
        $this->get(route('analytics.index'))->assertSee('547074352');

        $this->post(route('analytics.store'), ['name' => 'Again', 'url' => 'https://again.test', 'property_id' => '547074352'])->assertSessionHasErrors('property_id');
        $this->post(route('analytics.store'), ['name' => 'Wrong', 'url' => 'https://wrong.test', 'property_id' => 'G-ABC123XYZ'])->assertSessionHasErrors('property_id');
        $this->assertSame(1, AnalyticsSite::count());

        $this->post(route('analytics.store'), ['name' => 'Health only', 'url' => 'https://plain.test'])->assertRedirect();
        $this->assertSame(1, AnalyticsSite::count());
        $this->assertSame(2, Monitor::count());

        $this->delete(route('analytics.destroy', $site))->assertRedirect(route('analytics.index'));
        $this->assertSame(0, AnalyticsSite::count());
    }
}
