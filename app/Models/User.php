<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['username', 'email', 'password', 'job_scout_email'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The monitors whose domain expiry reminders this user receives.
     *
     * @return BelongsToMany<Monitor, $this>
     */
    public function monitorSubscriptions(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class);
    }

    /** @return HasMany<Reminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /** @return HasMany<WorldClock, $this> */
    public function worldClocks(): HasMany
    {
        return $this->hasMany(WorldClock::class);
    }

    /** @return HasMany<CalendarSource, $this> */
    public function calendarSources(): HasMany
    {
        return $this->hasMany(CalendarSource::class);
    }

    /** @return HasMany<BookingType, $this> */
    public function bookingTypes(): HasMany
    {
        return $this->hasMany(BookingType::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'digest_subscribed' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
