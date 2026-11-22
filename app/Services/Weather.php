<?php

namespace App\Services;

use App\Models\WorldClock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Current weather and sunrise/sunset for a clock's city, from Open-Meteo (free, no key). Everything is cached, and a failure just hides the weather.
 */
class Weather
{
    /** @var array<int, array{string, string}> WMO weather code ranges mapped to an icon and a label. */
    private const CODES = [
        [0, '☀️', 'Clear'], [1, '🌤️', 'Mostly clear'], [2, '⛅', 'Partly cloudy'], [3, '☁️', 'Overcast'],
        [45, '🌫️', 'Fog'], [48, '🌫️', 'Fog'], [51, '🌦️', 'Drizzle'], [53, '🌦️', 'Drizzle'], [55, '🌦️', 'Drizzle'],
        [61, '🌧️', 'Rain'], [63, '🌧️', 'Rain'], [65, '🌧️', 'Heavy rain'], [66, '🌧️', 'Freezing rain'], [67, '🌧️', 'Freezing rain'],
        [71, '🌨️', 'Snow'], [73, '🌨️', 'Snow'], [75, '❄️', 'Heavy snow'], [77, '🌨️', 'Snow grains'],
        [80, '🌦️', 'Showers'], [81, '🌧️', 'Showers'], [82, '⛈️', 'Heavy showers'], [85, '🌨️', 'Snow showers'], [86, '🌨️', 'Snow showers'],
        [95, '⛈️', 'Thunderstorm'], [96, '⛈️', 'Thunderstorm'], [99, '⛈️', 'Thunderstorm'],
    ];

    /**
     * @return array{temp: int, icon: string, label: string, sunrise: string, sunset: string}|null
     */
    public function for(WorldClock $clock): ?array
    {
        $place = $this->place($clock);
        if ($place === null) {
            return null;
        }

        $result = Cache::remember('weather.'.$clock->timezone, now()->addMinutes(30), function () use ($place): array|false {
            try {
                $response = Http::timeout(4)->acceptJson()->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $place['lat'], 'longitude' => $place['lon'], 'current' => 'temperature_2m,weather_code',
                    'daily' => 'sunrise,sunset', 'timezone' => 'auto', 'forecast_days' => 1,
                ]);
            } catch (Throwable) {
                return false;
            }
            if (! $response->successful() || $response->json('current.temperature_2m') === null) {
                return false;
            }
            [$icon, $label] = $this->describe((int) $response->json('current.weather_code'));

            return [
                'temp' => (int) round($response->json('current.temperature_2m')),
                'icon' => $icon,
                'label' => $label,
                'sunrise' => substr((string) $response->json('daily.sunrise.0'), 11, 5),
                'sunset' => substr((string) $response->json('daily.sunset.0'), 11, 5),
            ];
        });

        return $result ?: null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function describe(int $code): array
    {
        $match = [self::CODES[0][1], self::CODES[0][2]];
        foreach (self::CODES as [$from, $icon, $label]) {
            if ($code >= $from) {
                $match = [$icon, $label];
            }
        }

        return $match;
    }

    /**
     * @return array{lat: float, lon: float}|null
     */
    private function place(WorldClock $clock): ?array
    {
        $place = Cache::get('weather-place.'.$clock->timezone);
        if ($place !== null) {
            return $place ?: null;
        }

        try {
            $results = Http::timeout(4)->acceptJson()->get('https://geocoding-api.open-meteo.com/v1/search', ['name' => Str::before($clock->city, ' ('), 'count' => 10])->json('results') ?? [];
        } catch (Throwable) {
            return null;
        }

        $best = collect($results)->firstWhere('timezone', $clock->timezone) ?? ($results[0] ?? null);
        $place = $best === null ? false : ['lat' => (float) $best['latitude'], 'lon' => (float) $best['longitude']];
        Cache::put('weather-place.'.$clock->timezone, $place, $place === false ? now()->addDay() : now()->addYear());

        return $place ?: null;
    }
}
