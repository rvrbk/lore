<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    public const MAX_PER_ITEM = 10;

    protected $fillable = ['name'];

    public function getRouteKeyName(): string
    {
        return 'name';
    }

    public function pages(): MorphToMany
    {
        return $this->morphedByMany(Page::class, 'taggable');
    }

    public function tasks(): MorphToMany
    {
        return $this->morphedByMany(Task::class, 'taggable');
    }

    /**
     * Turn "Ops, #on call, ops" into ['ops', 'on-call'].
     *
     * @param  string|array<int, string>|null  $input
     * @return array<int, string>
     */
    public static function normalize(string|array|null $input): array
    {
        $parts = is_array($input) ? $input : explode(',', (string) $input);

        return collect($parts)
            ->map(fn ($part) => Str::limit(Str::slug(ltrim(trim((string) $part), '#')), 30, ''))
            ->filter()
            ->unique()
            ->take(self::MAX_PER_ITEM)
            ->values()
            ->all();
    }
}
