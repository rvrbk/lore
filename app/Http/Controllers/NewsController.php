<?php

namespace App\Http\Controllers;

use App\Models\NewsItem;
use App\Services\NewsHeadlines;
use Illuminate\Http\JsonResponse;

class NewsController extends Controller
{
    /**
     * Ticker lines: the news nwsfd has harvested (alerts first) with the public feed's headlines mixed in between.
     */
    public function __invoke(NewsHeadlines $fallback): JsonResponse
    {
        $items = NewsItem::forTicker()->limit(20)->get()->map(fn (NewsItem $item) => [
            'kind' => $item->is_alert ? 'alert' : 'news',
            'tag' => $item->is_alert ? null : ($item->category ?: $item->source_name),
            'text' => $item->title.($item->source_name ? " ({$item->source_name})" : ''),
        ]);

        $headlines = collect($fallback->latest())->map(fn (string $text) => ['kind' => 'headline', 'tag' => null, 'text' => $text]);

        $mixed = [];
        foreach (range(0, max($items->count(), $headlines->count()) - 1) as $index) {
            foreach ([$items->get($index), $headlines->get($index)] as $line) {
                if ($line !== null) {
                    $mixed[] = $line;
                }
            }
        }

        return response()->json(['items' => $mixed]);
    }
}
