<?php

namespace App\Models;

use Database\Factories\WorldClockFactory;
use DateTimeZone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A city whose local time is shown as a clock on the dashboard.
 */
class WorldClock extends Model
{
    /** @use HasFactory<WorldClockFactory> */
    use HasFactory;

    protected $fillable = ['city', 'timezone', 'position', 'is_analog', 'is_header'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_analog' => 'boolean', 'is_header' => 'boolean'];
    }

    /**
     * Every city the dashboard can show, keyed by its time zone identifier.
     *
     * @return array<string, string>
     */
    public static function cities(): array
    {
        $cities = [];
        foreach (DateTimeZone::listIdentifiers(DateTimeZone::ALL) as $identifier) {
            if (! str_contains($identifier, '/')) {
                continue;
            }
            $parts = explode('/', $identifier);
            $cities[$identifier] = str_replace('_', ' ', end($parts)).' ('.str_replace('_', ' ', $parts[0]).')';
        }
        asort($cities);

        return $cities;
    }
}
