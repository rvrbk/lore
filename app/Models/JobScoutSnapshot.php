<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/**
 * The number of new Job Scout matches a user had on a day, so the dashboard can show a trend.
 */
class JobScoutSnapshot extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'day', 'new_matches'];

    protected function casts(): array
    {
        return ['day' => 'date:Y-m-d'];
    }

    /**
     * The last N days of snapshots as one number per day, oldest first, with days without a snapshot carried over from the day before (zero at the start).
     *
     * @return array<int, int>
     */
    public static function trend(int $days = 30): array
    {
        $byDay = static::where('day', '>=', today()->subDays($days - 1))->pluck('new_matches', 'day')->mapWithKeys(fn (int $count, string $day) => [substr($day, 0, 10) => $count]);
        $previous = static::where('day', '<', today()->subDays($days - 1))->orderByDesc('day')->value('new_matches') ?? 0;
        $values = [];
        foreach (range($days - 1, 0) as $ago) {
            $previous = $byDay->get(today()->subDays($ago)->toDateString(), $previous);
            $values[] = $previous;
        }

        return $values;
    }
}
