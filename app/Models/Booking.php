<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A meeting a guest booked through Pointy. Times are stored in UTC.
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected $fillable = ['booking_type_id', 'guest_name', 'guest_email', 'guest_timezone', 'notes', 'starts_at', 'ends_at', 'token', 'cancelled_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /**
     * Instants are written in the application's time zone, whatever zone the given date object carries.
     *
     * @return Attribute<mixed, mixed>
     */
    protected function startsAt(): Attribute
    {
        return Attribute::set(fn (mixed $value) => Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'));
    }

    /** @return Attribute<mixed, mixed> */
    protected function endsAt(): Attribute
    {
        return Attribute::set(fn (mixed $value) => Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'));
    }

    /** @return BelongsTo<BookingType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(BookingType::class, 'booking_type_id');
    }

    /** @param Builder<Booking> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('cancelled_at');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
