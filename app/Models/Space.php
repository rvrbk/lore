<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Space extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    protected static function booted(): void
    {
        static::creating(function (Space $space) {
            $space->slug = static::uniqueSlug($space->slug ?: $space->name);
        });
    }

    public static function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'space';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /** @return Collection<int|string, Collection<int, Page>> pages grouped by parent id ('' = root) */
    public function tree(): Collection
    {
        return $this->pages()->orderBy('position')->orderBy('title')->get()->groupBy(fn (Page $p) => $p->parent_id ?? '');
    }
}
