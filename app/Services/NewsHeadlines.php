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
        return $this->cached('news.headlines', 'services.news.feed', 20);
    }

    /**
     * Recent UFO sighting news from a public RSS feed.
     *
     * @return array<int, string>
     */
    public function ufo(): array
    {
        return $this->cached('news.ufo', 'services.news.ufo_feed', 10);
    }

    /**
     * @return array<int, string>
     */
    private function cached(string $cacheKey, string $configKey, int $limit): array
    {
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $headlines = $this->fetch(config($configKey), $limit);
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
