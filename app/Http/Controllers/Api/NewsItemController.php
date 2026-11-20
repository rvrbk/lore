<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsItemController extends Controller
{
    /**
     * Receive the relevant items nwsfd has harvested; authenticated with a shared bearer token.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $token = (string) config('services.nwsfd.token');
        abort_if($token === '', 503, 'The news push is not configured.');
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401);

        $data = $request->validate([
            'items' => ['present', 'array', 'max:100'],
            'items.*.id' => ['required', 'string', 'max:255'],
            'items.*.title' => ['required', 'string', 'max:300'],
            'items.*.source_name' => ['nullable', 'string', 'max:100'],
            'items.*.region' => ['nullable', 'string', 'max:50'],
            'items.*.category' => ['nullable', 'string', 'max:50'],
            'items.*.url' => ['nullable', 'url:http,https', 'max:2000'],
            'items.*.relevance' => ['required', 'integer', 'between:0,10'],
            'items.*.alert' => ['required', 'boolean'],
            'items.*.published_at' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($data['items'] as $item) {
            NewsItem::updateOrCreate(['external_id' => $item['id']], [
                'title' => $item['title'],
                'source_name' => $item['source_name'] ?? null,
                'region' => $item['region'] ?? null,
                'category' => $item['category'] ?? null,
                'url' => $item['url'] ?? null,
                'relevance' => $item['relevance'],
                'is_alert' => $item['alert'],
                'published_at' => now()->createFromTimestampMs($item['published_at']),
            ]);
        }
        NewsItem::where('published_at', '<', now()->subDays(NewsItem::KEEP_DAYS))->delete();

        return response()->json(['stored' => count($data['items'])]);
    }
}
