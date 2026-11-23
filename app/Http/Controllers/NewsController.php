<?php

namespace App\Http\Controllers;

use App\Models\NewsItem;
use App\Services\NewsHeadlines;
use Illuminate\Http\JsonResponse;

class NewsController extends Controller
{
    /**
     * Ticker lines: the news nwsfd has harvested (alerts first) with the public feeds' headlines (several world, tech and science sources) and recent UFO sighting news mixed in between, one lane per source and without repeats.
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
        $extras = collect($fallback->extras())->map(fn (array $feed) => collect($feed['headlines'])->map(fn (string $text) => ['kind' => $feed['tag'] === null ? 'headline' : 'news', 'tag' => $feed['tag'], 'text' => $text]));

        $lanes = collect([$items, $headlines, $tech, $world, $ufo])->concat($extras);

        $mixed = [];
        $seen = [];
        foreach (range(0, max(0, $lanes->max(fn ($lane) => $lane->count()) - 1)) as $index) {
            foreach ($lanes as $lane) {
                $line = $lane->get($index);
                if ($line !== null && ! isset($seen[mb_strtolower($line['text'])])) {
                    $seen[mb_strtolower($line['text'])] = true;
                    $mixed[] = $line;
                }
            }
        }

        return response()->json(['items' => $mixed]);
    }
}
