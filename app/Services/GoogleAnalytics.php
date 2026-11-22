<?php

namespace App\Services;

use App\Models\AnalyticsSite;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Last-7-days totals per GA4 property via the Data API, authenticated with a service account (no extra packages).
 */
class GoogleAnalytics
{
    private const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    public function hasCredentials(): bool
    {
        return is_readable((string) config('services.google_analytics.credentials'));
    }

    public function isConfigured(): bool
    {
        return $this->hasCredentials() && AnalyticsSite::exists();
    }

    public function forget(): void
    {
        Cache::forget('google-analytics.summary');
        Cache::forget('google-analytics.live-sites');
    }

    /**
     * @return array<int, array{name: string, users: int|null, sessions: int|null, views: int|null, daily: array<int, int>, countries: array<string, int>}>|null
     */
    public function summary(): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $result = $this->rememberUnlessFailed('google-analytics.summary', now()->addMinutes(15), function (): array|false {
            try {
                $token = $this->accessToken();
                $rows = [];
                foreach (AnalyticsSite::orderBy('name')->pluck('property_id', 'name') as $name => $id) {
                    $response = Http::withToken($token)->timeout(8)->post("https://analyticsdata.googleapis.com/v1beta/properties/{$id}:runReport", [
                        'dateRanges' => [['startDate' => '6daysAgo', 'endDate' => 'today']],
                        'dimensions' => [['name' => 'date']],
                        'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions'], ['name' => 'screenPageViews']],
                        'metricAggregations' => ['TOTAL'],
                    ]);
                    if (! $response->successful()) {
                        $rows[] = ['name' => $name, 'users' => null, 'sessions' => null, 'views' => null, 'daily' => [], 'countries' => []];

                        continue;
                    }

                    $byDay = collect($response->json('rows') ?? [])->mapWithKeys(fn (array $row) => [$row['dimensionValues'][0]['value'] => (int) ($row['metricValues'][0]['value'] ?? 0)]);
                    $daily = collect(range(6, 0))->map(fn (int $ago) => $byDay->get(now()->subDays($ago)->format('Ymd'), 0))->all();
                    $totals = $response->json('totals.0.metricValues') ?? [];
                    $countryReport = Http::withToken($token)->timeout(8)->post("https://analyticsdata.googleapis.com/v1beta/properties/{$id}:runReport", [
                        'dateRanges' => [['startDate' => '6daysAgo', 'endDate' => 'today']],
                        'dimensions' => [['name' => 'countryId']],
                        'metrics' => [['name' => 'activeUsers']],
                        'limit' => 250,
                    ]);
                    $countries = $countryReport->successful()
                        ? collect($countryReport->json('rows') ?? [])->mapWithKeys(fn (array $row) => [strtoupper($row['dimensionValues'][0]['value']) => (int) ($row['metricValues'][0]['value'] ?? 0)])->all()
                        : [];
                    $rows[] = [
                        'name' => $name,
                        'users' => (int) ($totals[0]['value'] ?? 0),
                        'sessions' => (int) ($totals[1]['value'] ?? 0),
                        'views' => (int) ($totals[2]['value'] ?? 0),
                        'daily' => $daily,
                        'countries' => $countries,
                    ];
                }

                return $rows;
            } catch (Throwable) {
                return false;
            }
        });

        return $result ?: null;
    }

    /**
     * Users active in the last five minutes, summed over all sites (GA4 Realtime API); null when unavailable.
     */
    public function liveUsers(): ?int
    {
        $sites = $this->liveBySite();

        return $sites === null ? null : array_sum($sites);
    }

    /**
     * Users active in the last five minutes per site name (GA4 Realtime API), busiest first; sites that did not answer are left out, null when none did.
     *
     * @return array<string, int>|null
     */
    public function liveBySite(): ?array
    {
        return $this->liveReport()['sites'] ?? null;
    }

    /**
     * Users active in the last five minutes per country (ISO 3166-1 alpha-2) over all sites, busiest first; null when unavailable.
     *
     * @return array<string, int>|null
     */
    public function liveByCountry(): ?array
    {
        return $this->liveReport()['countries'] ?? null;
    }

    /**
     * @return array{sites: array<string, int>, countries: array<string, int>}|null
     */
    private function liveReport(): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $result = $this->rememberUnlessFailed('google-analytics.live-report', now()->addSeconds(30), function (): array|false {
            try {
                $token = $this->accessToken();
                $sites = [];
                $countries = [];
                $answered = false;
                foreach (AnalyticsSite::orderBy('name')->pluck('property_id', 'name') as $name => $id) {
                    $response = Http::withToken($token)->timeout(8)->post("https://analyticsdata.googleapis.com/v1beta/properties/{$id}:runRealtimeReport", [
                        'dimensions' => [['name' => 'countryId']],
                        'metrics' => [['name' => 'activeUsers']],
                        'minuteRanges' => [['startMinutesAgo' => 4, 'endMinutesAgo' => 0]],
                    ]);
                    if ($response->successful()) {
                        $answered = true;
                        $sites[$name] = 0;
                        foreach ($response->json('rows') ?? [] as $row) {
                            $users = (int) ($row['metricValues'][0]['value'] ?? 0);
                            $code = strtoupper((string) ($row['dimensionValues'][0]['value'] ?? ''));
                            $sites[$name] += $users;
                            if (preg_match('/^[A-Z]{2}$/', $code)) {
                                $countries[$code] = ($countries[$code] ?? 0) + $users;
                            }
                        }
                    }
                }
                arsort($sites);
                arsort($countries);

                return $answered ? ['sites' => $sites, 'countries' => $countries] : false;
            } catch (Throwable) {
                return false;
            }
        });

        return $result === false ? null : $result;
    }

    /**
     * Users per country (ISO 3166-1 alpha-2) summed over all sites, biggest first.
     *
     * @param  array<int, array{countries?: array<string, int>}>  $summary
     * @return array<string, int>
     */
    public function countryTotals(array $summary): array
    {
        $totals = [];
        foreach ($summary as $site) {
            foreach ($site['countries'] ?? [] as $code => $users) {
                $totals[$code] = ($totals[$code] ?? 0) + $users;
            }
        }
        arsort($totals);

        return $totals;
    }

    /**
     * Country outlines, as SVG path data keyed by ISO code (Natural Earth, public domain).
     *
     * @return array{width: int, height: int, countries: array<string, array{name: string, d: string}>}
     */
    public function worldMap(): array
    {
        static $map = null;

        return $map ??= json_decode((string) file_get_contents(resource_path('data/world-map.json')), true);
    }

    /**
     * Like Cache::remember, but a failed fetch (false) is never cached, so one transient error cannot blank the dashboard.
     *
     * @template T
     *
     * @param  callable(): (T|false)  $callback
     * @return T|false
     */
    private function rememberUnlessFailed(string $key, \DateTimeInterface $ttl, callable $callback): mixed
    {
        $cached = Cache::get($key);
        if ($cached !== null && $cached !== false) {
            return $cached;
        }

        $value = $callback();
        if ($value !== false) {
            Cache::put($key, $value, $ttl);
        }

        return $value;
    }

    private function accessToken(): string
    {
        return Cache::remember('google-analytics.token', now()->addMinutes(50), function (): string {
            $key = json_decode((string) file_get_contents(config('services.google_analytics.credentials')), true);
            $encode = fn (string $data): string => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
            $now = time();
            $unsigned = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode(json_encode([
                'iss' => $key['client_email'], 'scope' => self::SCOPE, 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
            ]));
            openssl_sign($unsigned, $signature, $key['private_key'], OPENSSL_ALGO_SHA256);

            return Http::asForm()->timeout(8)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.$encode($signature),
            ])->throw()->json('access_token');
        });
    }
}
