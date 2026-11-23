<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Latest headlines from a public RSS feed (BBC World by default), cached for the map ticker.
 */
class NewsHeadlines
{
    /**
     * @return array<int, string>
     */
    public function latest(): array
    {
        return $this->cached('news.headlines', config('services.news.feed'), 20);
    }

    /**
     * World headlines from a second source (The Guardian by default).
     *
     * @return array<int, string>
     */
    public function world(): array
    {
        return $this->cached('news.world', config('services.news.world_feed'), 15);
    }

    /**
     * Tech headlines (Ars Technica by default).
     *
     * @return array<int, string>
     */
    public function tech(): array
    {
        return $this->cached('news.tech', config('services.news.tech_feed'), 15);
    }

    /**
     * Recent UFO sighting news from a public RSS feed.
     *
     * @return array<int, string>
     */
    public function ufo(): array
    {
        return $this->cached('news.ufo', config('services.news.ufo_feed'), 6);
    }

    /**
     * Headlines from each extra source, one list per feed, so the ticker can give every source its turn.
     *
     * @return array<int, array{tag: string|null, headlines: array<int, string>}>
     */
    public function extras(): array
    {
        return collect((array) config('services.news.extra_feeds'))
            ->map(fn (array $feed) => ['tag' => $feed['tag'] ?? null, 'headlines' => $this->cached('news.extra.'.md5($feed['url']), $feed['url'], 8)])
            ->filter(fn (array $feed) => $feed['headlines'] !== [])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function cached(string $cacheKey, mixed $url, int $limit): array
    {
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $headlines = $this->fetch($url, $limit);
        if ($headlines !== []) {
            Cache::put($cacheKey, $headlines, now()->addMinutes(10));
        }

        return $headlines;
    }

    /**
     * @return array<int, string>
     */
    private function fetch(mixed $url, int $limit): array
    {
        if (! is_string($url) || $url === '') {
            return [];
        }

        try {
            $response = Http::timeout(8)->get($url);
            if (! $response->successful()) {
                return [];
            }

            $previous = libxml_use_internal_errors(true);
            $feed = simplexml_load_string($response->body(), options: LIBXML_NONET);
            libxml_use_internal_errors($previous);
            if ($feed === false) {
                return [];
            }

            $titles = [];
            foreach ($feed->channel->item ?? [] as $item) {
                $title = trim(html_entity_decode((string) $item->title, ENT_QUOTES | ENT_HTML5));
                if ($title !== '') {
                    $titles[] = mb_substr($title, 0, 160);
                }
            }

            return array_slice($titles, 0, $limit);
        } catch (Throwable) {
            return [];
        }
    }
}
