<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Page extends Model
{
    protected $fillable = ['space_id', 'parent_id', 'title', 'body', 'position'];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('position')->orderBy('title');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->latest('id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /** Ids of this page and everything below it. */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $queue = [$this->id];
        while ($queue) {
            $children = static::whereIn('parent_id', $queue)->pluck('id')->all();
            $ids = array_merge($ids, $children);
            $queue = $children;
        }

        return $ids;
    }

    /** Ancestors from the root down to (not including) this page. */
    public function breadcrumbs(): array
    {
        $trail = [];
        $node = $this->parent;
        while ($node && count($trail) < 50) {
            array_unshift($trail, $node);
            $node = $node->parent;
        }

        return $trail;
    }

    public function html(): string
    {
        return Str::markdown($this->body ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function snippet(string $needle, int $radius = 70): string
    {
        $text = strip_tags($this->body ?? '');
        $pos = mb_stripos($text, $needle);
        if ($pos === false) {
            return Str::limit($text, $radius * 2);
        }
        $start = max(0, $pos - $radius);

        return ($start > 0 ? '…' : '').trim(mb_substr($text, $start, $radius * 2 + mb_strlen($needle))).'…';
    }
}
