<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A meeting a guest booked through Pointy. Times are stored in UTC.
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /** How long before the meeting its reminder comes due. */
    public const REMINDER_MINUTES_BEFORE = 15;

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

    /** @return HasMany<Reminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /** Puts a reminder in Lore shortly before the meeting starts (right away when it starts sooner than that). */
    public function scheduleReminder(): void
    {
        $this->loadMissing('type');
        $timezone = $this->type->timezone;

        $this->reminders()->create([
            'title' => "Meeting: {$this->type->name} with {$this->guest_name}",
            'notes' => $this->starts_at->copy()->setTimezone($timezone)->format('D j M, '.Setting::timePattern()).' ('.str_replace('_', ' ', $timezone).') · '.$this->guest_email,
            'due_at' => $this->starts_at->copy()->subMinutes(self::REMINDER_MINUTES_BEFORE)->max(now()),
        ]);
    }

    /** Cancels the booking and drops its reminder. */
    public function cancel(): void
    {
        $this->update(['cancelled_at' => now()]);
        $this->reminders()->delete();
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
