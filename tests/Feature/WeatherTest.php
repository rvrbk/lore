<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorldClock;
use App\Services\Weather;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherTest extends TestCase
{
    use RefreshDatabase;

    private function fakeOpenMeteo(): void
    {
        Cache::flush();
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response(['results' => [
                ['latitude' => 1.0, 'longitude' => 2.0, 'timezone' => 'America/Lima'],
                ['latitude' => 52.37, 'longitude' => 4.89, 'timezone' => 'Europe/Amsterdam'],
            ]]),
            'api.open-meteo.com/*' => Http::response(['current' => ['temperature_2m' => 11.6, 'weather_code' => 61], 'daily' => ['sunrise' => ['2026-11-22T07:41'], 'sunset' => ['2026-11-22T16:30']]]),
        ]);
    }

    public function test_weather_and_sun_times_are_read_for_the_clocks_own_time_zone(): void
    {
        $this->fakeOpenMeteo();
        $clock = WorldClock::factory()->create(['city' => 'Amsterdam (Europe)', 'timezone' => 'Europe/Amsterdam']);

        $weather = app(Weather::class)->for($clock);

        $this->assertSame(['temp' => 12, 'icon' => '🌧️', 'label' => 'Rain', 'sunrise' => '07:41', 'sunset' => '16:30'], $weather);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'latitude=52.37'));
    }

    public function test_the_weather_endpoint_answers_per_time_zone_and_survives_an_outage(): void
    {
        $this->actingAs(User::factory()->create());
        WorldClock::factory()->create(['city' => 'Amsterdam (Europe)', 'timezone' => 'Europe/Amsterdam']);

        $this->fakeOpenMeteo();
        $this->getJson(route('weather'))->assertOk()->assertJsonPath('Europe/Amsterdam.temp', 12);

        Cache::flush();
        Http::swap(new Factory);
        Http::fake(['*' => Http::response('', 500)]);
        $this->getJson(route('weather'))->assertOk()->assertExactJson([]);
    }

    public function test_clear_and_partly_cloudy_skies_get_night_icons_after_dark(): void
    {
        $weather = new Weather;

        $this->assertSame('☀️', $weather->describe(0)[0]);
        $this->assertSame('🌙', $weather->describe(0, false)[0]);
        $this->assertSame('☁️', $weather->describe(2, false)[0]);
        $this->assertSame('🌧️', $weather->describe(61, false)[0]);
    }
}
