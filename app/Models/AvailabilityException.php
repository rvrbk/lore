<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\AvailabilityExceptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Overrides the weekly hours of a booking type for one calendar day (in the type's time zone):
 * without times the day is closed, with times those hours apply instead, even on a day that is normally off.
 */
class AvailabilityException extends Model
{
    /** @use HasFactory<AvailabilityExceptionFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = ['booking_type_id', 'date', 'start_time', 'end_time', 'note'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }

    /** @return BelongsTo<BookingType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(BookingType::class, 'booking_type_id');
    }

    public function isClosed(): bool
    {
        return blank($this->start_time) || blank($this->end_time);
    }
}
