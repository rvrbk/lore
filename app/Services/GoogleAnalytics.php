<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Last-7-days totals per GA4 property via the Data API, authenticated with a service account (no extra packages).
 */
class GoogleAnalytics
{
    private const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    public function isConfigured(): bool
    {
        return $this->properties() !== [] && is_readable((string) config('services.google_analytics.credentials'));
    }

    /**
     * Properties are configured as "Name:propertyId" pairs, comma separated.
     *
     * @return array<string, string>
     */
    public function properties(): array
    {
        $properties = [];
        foreach (array_filter(array_map('trim', explode(',', (string) config('services.google_analytics.properties')))) as $pair) {
            [$name, $id] = array_pad(explode(':', $pair, 2), 2, '');
            if (ctype_digit(trim($id)) && trim($name) !== '') {
                $properties[trim($name)] = trim($id);
            }
        }

        return $properties;
    }

    /**
     * @return array<int, array{name: string, users: int|null, sessions: int|null, views: int|null, daily: array<int, int>}>|null
     */
    public function summary(): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $result = Cache::remember('google-analytics.summary', now()->addMinutes(15), function (): array|false {
            try {
                $token = $this->accessToken();
                $rows = [];
                foreach ($this->properties() as $name => $id) {
                    $response = Http::withToken($token)->timeout(8)->post("https://analyticsdata.googleapis.com/v1beta/properties/{$id}:runReport", [
                        'dateRanges' => [['startDate' => '6daysAgo', 'endDate' => 'today']],
                        'dimensions' => [['name' => 'date']],
                        'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions'], ['name' => 'screenPageViews']],
                        'metricAggregations' => ['TOTAL'],
                    ]);
                    if (! $response->successful()) {
                        $rows[] = ['name' => $name, 'users' => null, 'sessions' => null, 'views' => null, 'daily' => []];

                        continue;
                    }

                    $byDay = collect($response->json('rows') ?? [])->mapWithKeys(fn (array $row) => [$row['dimensionValues'][0]['value'] => (int) ($row['metricValues'][0]['value'] ?? 0)]);
                    $daily = collect(range(6, 0))->map(fn (int $ago) => $byDay->get(now()->subDays($ago)->format('Ymd'), 0))->all();
                    $totals = $response->json('totals.0.metricValues') ?? [];
                    $rows[] = [
                        'name' => $name,
                        'users' => (int) ($totals[0]['value'] ?? 0),
                        'sessions' => (int) ($totals[1]['value'] ?? 0),
                        'views' => (int) ($totals[2]['value'] ?? 0),
                        'daily' => $daily,
                    ];
                }

                return $rows;
            } catch (Throwable) {
                return false;
            }
        });

        return $result ?: null;
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
