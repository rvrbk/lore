<?php

namespace App\Models;

use Database\Factories\PointySiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A website (global, shared by every user) that embeds or links to Pointy with `?site={key}` and gives its booking pages its own CSS.
 */
class PointySite extends Model
{
    /** @use HasFactory<PointySiteFactory> */
    use HasFactory;

    protected $fillable = ['name', 'key', 'css'];

    /**
     * The site's own CSS, safe to print inside a style tag.
     */
    public function publicCss(): string
    {
        return trim(str_ireplace('<', '\\3c ', (string) $this->css));
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
