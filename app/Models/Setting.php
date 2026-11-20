<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * System-wide settings kept as key/value pairs.
 */
class Setting extends Model
{
    public const TIME_FORMAT = 'time_format';

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->whereKey($key)->value('value') ?? $default;
    }

    public static function put(string $key, string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** Whether times are shown as "12" or "24" hour notation. */
    public static function timeFormat(): string
    {
        return static::get(self::TIME_FORMAT, '24') === '12' ? '12' : '24';
    }

    public static function uses12Hours(): bool
    {
        return static::timeFormat() === '12';
    }

    /** PHP date format for a clock time, in the chosen notation. */
    public static function timePattern(bool $seconds = false): string
    {
        if (static::uses12Hours()) {
            return $seconds ? 'g:i:s A' : 'g:i A';
        }

        return $seconds ? 'H:i:s' : 'H:i';
    }
}
