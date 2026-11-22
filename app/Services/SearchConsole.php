<?php

namespace App\Services;

use App\Models\Monitor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * How a site does in Google Search, from the Search Console API: clicks, impressions, ranking, top queries, sitemap problems
 * and whether the home page is indexed. Uses the Analytics service account, which must be added as a user on each property.
 */
class SearchConsole
{
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    private const API = 'https://www.googleapis.com/webmasters/v3/sites';

    /** Days of search data shown. Google reports a day or two late, so the newest days are often still zero. */
    public const DAYS = 28;

    public function __construct(private GoogleServiceAccount $account) {}

    public function isConfigured(): bool
    {
        return $this->account->hasCredentials();
    }

    /**
     * The properties the service account can see, as Search Console writes them ("sc-domain:example.com" or "https://example.com/").
     *
     * @return array<int, string>|null
     */
    public function properties(): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $cached = Cache::get('search-console.v1.properties');
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::withToken($this->account->token(self::SCOPE))->timeout(8)->get(self::API);
        } catch (Throwable) {
            return null;
        }
        if (! $response->successful()) {
            return null;
        }

        $properties = collect($response->json('siteEntry') ?? [])->pluck('siteUrl')->filter()->values()->all();
        Cache::put('search-console.v1.properties', $properties, now()->addHour());

        return $properties;
    }

    /**
     * The property that covers the monitor's site: a URL-prefix property the site's address starts with (the longest wins), else a domain property for its host or a parent domain.
     */
    public function propertyFor(Monitor $monitor): ?string
    {
        $properties = $this->properties() ?? [];
        $url = rtrim($monitor->url, '/').'/';
        $host = strtolower($monitor->host());

        $prefixes = array_filter($properties, fn (string $property) => ! str_starts_with($property, 'sc-domain:') && str_starts_with($url, rtrim($property, '/').'/'));
        if ($prefixes !== []) {
            usort($prefixes, fn (string $a, string $b) => strlen($b) <=> strlen($a));

            return $prefixes[0];
        }

        foreach ($properties as $property) {
            if (str_starts_with($property, 'sc-domain:')) {
                $domain = strtolower(substr($property, 10));
                if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                    return $property;
                }
            }
        }

        return null;
    }

    /**
     * @return array{property: string, daily_clicks: array<int, int>, daily_impressions: array<int, int>, clicks: int, impressions: int, ctr: float, position: float|null, queries: array<int, array{query: string, clicks: int, impressions: int, position: float}>, sitemap_problems: int|null, home: array{verdict: string, state: string}|null}|null Null when the site is not in Search Console or the API did not answer.
     */
    public function report(Monitor $monitor): ?array
    {
        $property = $this->propertyFor($monitor);
        if ($property === null) {
            return null;
        }

        $key = 'search-console.v1.report.'.$monitor->id;
        $cached = Cache::get($key);
        if (is_array($cached) && $cached['property'] === $property) {
            return $cached;
        }

        $report = $this->fetch($monitor, $property);
        if ($report !== null) {
            Cache::put($key, $report, now()->addHour());
        }

        return $report;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(Monitor $monitor, string $property): ?array
    {
        try {
            $token = $this->account->token(self::SCOPE);
            $site = rawurlencode($property);
            $filter = $this->pageFilter($monitor, $property);
            $range = ['startDate' => now()->subDays(self::DAYS - 1)->toDateString(), 'endDate' => now()->toDateString()];

            $daily = Http::withToken($token)->timeout(10)->post(self::API."/{$site}/searchAnalytics/query", $range + ['dimensions' => ['date'], 'rowLimit' => 100] + $filter);
            if (! $daily->successful()) {
                return null;
            }
            $byDay = collect($daily->json('rows') ?? [])->keyBy(fn (array $row) => $row['keys'][0]);
            $clicks = [];
            $impressions = [];
            foreach (range(self::DAYS - 1, 0) as $ago) {
                $row = $byDay->get(now()->subDays($ago)->toDateString());
                $clicks[] = (int) ($row['clicks'] ?? 0);
                $impressions[] = (int) ($row['impressions'] ?? 0);
            }
            $totalImpressions = array_sum($impressions);
            $weighted = $byDay->sum(fn (array $row) => ($row['position'] ?? 0) * ($row['impressions'] ?? 0));

            $queries = Http::withToken($token)->timeout(10)->post(self::API."/{$site}/searchAnalytics/query", $range + ['dimensions' => ['query'], 'rowLimit' => 10] + $filter);

            return [
                'property' => $property,
                'daily_clicks' => $clicks,
                'daily_impressions' => $impressions,
                'clicks' => array_sum($clicks),
                'impressions' => $totalImpressions,
                'ctr' => $totalImpressions > 0 ? round(array_sum($clicks) / $totalImpressions * 100, 1) : 0.0,
                'position' => $totalImpressions > 0 ? round($weighted / $totalImpressions, 1) : null,
                'queries' => $queries->successful() ? collect($queries->json('rows') ?? [])->map(fn (array $row) => [
                    'query' => (string) $row['keys'][0], 'clicks' => (int) $row['clicks'], 'impressions' => (int) $row['impressions'], 'position' => round((float) $row['position'], 1),
                ])->all() : [],
                'sitemap_problems' => $this->sitemapProblems($token, $site),
                'home' => $this->homeStatus($token, $monitor, $property),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A domain property covers every subdomain, so a site on a subdomain only counts its own pages.
     *
     * @return array<string, mixed>
     */
    private function pageFilter(Monitor $monitor, string $property): array
    {
        if (! str_starts_with($property, 'sc-domain:') || strtolower($monitor->host()) === strtolower(substr($property, 10))) {
            return [];
        }

        return ['dimensionFilterGroups' => [['filters' => [['dimension' => 'page', 'operator' => 'contains', 'expression' => '://'.$monitor->host().'/']]]]];
    }

    private function sitemapProblems(string $token, string $site): ?int
    {
        $response = Http::withToken($token)->timeout(8)->get(self::API."/{$site}/sitemaps");
        if (! $response->successful()) {
            return null;
        }

        return (int) collect($response->json('sitemap') ?? [])->sum(fn (array $sitemap) => (int) ($sitemap['errors'] ?? 0));
    }

    /**
     * @return array{verdict: string, state: string}|null
     */
    private function homeStatus(string $token, Monitor $monitor, string $property): ?array
    {
        $response = Http::withToken($token)->timeout(10)->post('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', [
            'inspectionUrl' => rtrim($monitor->url, '/').'/', 'siteUrl' => $property,
        ]);
        $status = $response->json('inspectionResult.indexStatusResult');
        if (! $response->successful() || ! is_array($status)) {
            return null;
        }

        return ['verdict' => (string) ($status['verdict'] ?? 'VERDICT_UNSPECIFIED'), 'state' => (string) ($status['coverageState'] ?? '')];
    }
}
