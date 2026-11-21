<?php

namespace App\Http\Controllers;

use App\Models\NewsItem;
use App\Services\NewsHeadlines;
use Illuminate\Http\JsonResponse;

class NewsController extends Controller
{
    /**
     * Ticker lines: the news nwsfd has harvested (alerts first) with the public feeds' headlines (two world sources), tech news and UFO sighting news mixed in between.
     */
    public function __invoke(NewsHeadlines $fallback): JsonResponse
    {
        $items = NewsItem::forTicker()->limit(20)->get()->map(fn (NewsItem $item) => [
            'kind' => $item->is_alert ? 'alert' : 'news',
            'tag' => $item->is_alert ? null : ($item->category ?: $item->source_name),
            'text' => $item->title.($item->source_name ? " ({$item->source_name})" : ''),
        ]);

        $headlines = collect($fallback->latest())->map(fn (string $text) => ['kind' => 'headline', 'tag' => null, 'text' => $text]);
        $world = collect($fallback->world())->map(fn (string $text) => ['kind' => 'headline', 'tag' => null, 'text' => $text]);
        $tech = collect($fallback->tech())->map(fn (string $text) => ['kind' => 'news', 'tag' => 'Tech', 'text' => $text]);
        $ufo = collect($fallback->ufo())->map(fn (string $text) => ['kind' => 'news', 'tag' => 'UFO', 'text' => $text]);

        $mixed = [];
        foreach (range(0, max($items->count(), $headlines->count(), $world->count(), $tech->count(), $ufo->count()) - 1) as $index) {
            foreach ([$items->get($index), $headlines->get($index), $tech->get($index), $world->get($index), $ufo->get($index)] as $line) {
                if ($line !== null) {
                    $mixed[] = $line;
                }
            }
        }

        return response()->json(['items' => $mixed]);
    }
}
