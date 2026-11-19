<?php

namespace Tests\Feature;

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
        config(['services.google_analytics' => ['properties' => 'Verbeek:111, Broken:222, bad', 'credentials' => $path]]);
        Cache::flush();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            '*properties/111:runReport' => Http::response([
                'rows' => [['dimensionValues' => [['value' => now()->format('Ymd')]], 'metricValues' => [['value' => '5'], ['value' => '6'], ['value' => '7']]]],
                'totals' => [['metricValues' => [['value' => '1234'], ['value' => '2000'], ['value' => '9876']]]],
            ]),
            '*properties/222:runReport' => Http::response('denied', 403),
        ]);
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Verbeek')->assertSee('1,234')->assertSee('9,876')
            ->assertSee('Daily users for Verbeek')->assertSee('Broken')->assertSee('Could not load');

        unlink($path);
    }

    public function test_it_stays_hidden_when_not_configured(): void
    {
        $this->assertNull(app(GoogleAnalytics::class)->summary());
        $this->actingAs(User::factory()->create());
        $this->get(route('dashboard'))->assertOk()->assertDontSee('last 7 days');
    }
}
