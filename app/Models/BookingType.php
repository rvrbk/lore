<?php

namespace App\Models;

use Database\Factories\BookingTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of meeting Pointy lets people book: how long it lasts and in which weekly hours (in the owner's time zone).
 */
class BookingType extends Model
{
    /** @use HasFactory<BookingTypeFactory> */
    use HasFactory;

    protected $fillable = ['slug', 'name', 'description', 'duration_minutes', 'timezone', 'days', 'start_time', 'end_time', 'buffer_minutes', 'min_notice_hours', 'max_days_ahead', 'is_active', 'include_meet', 'meet_url'];

    protected function casts(): array
    {
        return ['days' => 'array', 'is_active' => 'boolean', 'include_meet' => 'boolean'];
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** The Google Meet link bookings of this type get, or null when Meet is switched off or no link is set. */
    public function meetingUrl(): ?string
    {
        if (! $this->include_meet) {
            return null;
        }

        return $this->meet_url ?: (config('services.pointy.meet_url') ?: null);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
