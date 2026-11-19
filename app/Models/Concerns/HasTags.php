<?php

namespace App\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->orderBy('name');
    }

    /**
     * Replace this model's tags with the given comma-separated names or list.
     *
     * @param  string|array<int, string>|null  $names
     */
    public function syncTagNames(string|array|null $names): void
    {
        $ids = collect(Tag::normalize($names))->map(fn (string $name) => Tag::firstOrCreate(['name' => $name])->id);

        $this->tags()->sync($ids->all());
        Tag::doesntHave('pages')->doesntHave('tasks')->delete();
    }

    public function tagList(): string
    {
        return $this->tags->pluck('name')->implode(', ');
    }

    public function scopeWithTag(Builder $query, ?string $tag): void
    {
        if ($tag !== null && $tag !== '') {
            $query->whereHas('tags', fn (Builder $q) => $q->where('name', $tag));
        }
    }
}
