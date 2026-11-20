<?php

namespace App\Models;

use Database\Factories\NewsItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A relevant news item harvested and scored by the nwsfd app, pushed to Lore for the map ticker.
 */
class NewsItem extends Model
{
    /** @use HasFactory<NewsItemFactory> */
    use HasFactory;

    /** Items older than this many days are dropped on the next push. */
    public const KEEP_DAYS = 7;

    /** The ticker only shows items published within this many hours. */
    public const TICKER_HOURS = 48;

    protected $fillable = ['external_id', 'title', 'source_name', 'region', 'category', 'url', 'relevance', 'is_alert', 'published_at'];

    protected function casts(): array
    {
        return ['is_alert' => 'boolean', 'published_at' => 'datetime'];
    }

    /** @param Builder<NewsItem> $query */
    public function scopeForTicker(Builder $query): void
    {
        $query->where('published_at', '>=', now()->subHours(self::TICKER_HOURS))
            ->orderByDesc('is_alert')
            ->orderByDesc('relevance')
            ->orderByDesc('published_at');
    }
}
