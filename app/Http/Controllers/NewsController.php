<?php

namespace App\Http\Controllers;

use App\Models\NewsItem;
use App\Services\NewsHeadlines;
use Illuminate\Http\JsonResponse;

class NewsController extends Controller
{
    /**
     * Ticker lines: the news nwsfd has harvested (alerts first), or a public feed when it has pushed nothing recent.
     */
    public function __invoke(NewsHeadlines $fallback): JsonResponse
    {
        $items = NewsItem::forTicker()->limit(20)->get()->map(fn (NewsItem $item) => [
            'kind' => $item->is_alert ? 'alert' : 'news',
            'tag' => $item->is_alert ? null : ($item->category ?: $item->source_name),
            'text' => $item->title.($item->source_name ? " ({$item->source_name})" : ''),
        ]);

        if ($items->isEmpty()) {
            $items = collect($fallback->latest())->map(fn (string $text) => ['kind' => 'headline', 'tag' => null, 'text' => $text]);
        }

        return response()->json(['items' => $items->values()]);
    }
}
