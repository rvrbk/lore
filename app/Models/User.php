<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['username', 'email', 'password', 'job_scout_email'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_USER = 'user';

    /** Admins are always subscribed to every monitor, now and for monitors added later. */
    public const ROLE_ADMIN = 'admin';

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function approve(): void
    {
        $this->forceFill(['approved_at' => now()])->save();
    }

    /**
     * Accounts that registered and wait for an admin to approve them.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('approved_at');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** Gives the user the admin role and subscribes them to every existing monitor. */
    public function makeAdmin(): void
    {
        $this->forceFill(['role' => self::ROLE_ADMIN])->save();
        $this->monitorSubscriptions()->syncWithoutDetaching(Monitor::pluck('id'));
    }

    /**
     * The monitors whose domain expiry reminders this user receives.
     *
     * @return BelongsToMany<Monitor, $this>
     */
    public function monitorSubscriptions(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Makes ten new recovery codes, keeps only their hashes, and returns the codes to show once.
     *
     * @return list<string>
     */
    public function resetRecoveryCodes(): array
    {
        $codes = array_map(fn () => strtolower(bin2hex(random_bytes(5))).'-'.strtolower(bin2hex(random_bytes(5))), range(1, 10));
        $this->forceFill(['two_factor_recovery_codes' => array_map(fn (string $code) => hash('sha256', $code), $codes)])->save();

        return $codes;
    }

    /** Spends a recovery code: it works once. */
    public function useRecoveryCode(string $code): bool
    {
        $hash = hash('sha256', strtolower(trim($code)));
        $codes = $this->two_factor_recovery_codes ?? [];
        $index = array_search($hash, $codes, true);
        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $this->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }

    /** @return HasMany<VaultItem, $this> */
    public function vaultItems(): HasMany
    {
        return $this->hasMany(VaultItem::class);
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
            'approved_at' => 'datetime',
            'digest_subscribed' => 'boolean',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
