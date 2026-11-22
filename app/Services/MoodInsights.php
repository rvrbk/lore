<?php

namespace App\Services;

use App\Models\Deployment;
use App\Models\Incident;
use App\Models\Mood;
use Carbon\Carbon;

/**
 * Sets the daily mood against what happened in the systems that day.
 */
class MoodInsights
{
    public const DAYS = 30;

    /**
     * Mood per day for the last 30 days, oldest first; days without an entry are null.
     *
     * @return array<int, array{day: string, level: int|null, rough: bool}>
     */
    public function days(): array
    {
        $since = today()->subDays(self::DAYS - 1);
        $levels = Mood::where('day', '>=', $since->toDateString())->get()->mapWithKeys(fn (Mood $mood) => [$mood->day->toDateString() => $mood->level]);
        $rough = $this->roughDays($since->copy());

        $days = [];
        for ($i = 0; $i < self::DAYS; $i++) {
            $day = $since->copy()->addDays($i)->toDateString();
            $days[] = ['day' => $day, 'level' => $levels->get($day), 'rough' => isset($rough[$day])];
        }

        return $days;
    }

    /**
     * A sentence comparing the mood on rough days (an incident or a failed deploy) with calm ones, once there is enough data.
     *
     * @param  array<int, array{day: string, level: int|null, rough: bool}>  $days
     */
    public function insight(array $days): ?string
    {
        $rated = collect($days)->whereNotNull('level');
        $rough = $rated->where('rough', true);
        $calm = $rated->where('rough', false);
        if ($rough->count() < 3 || $calm->count() < 3) {
            return null;
        }

        $difference = round($calm->avg('level') - $rough->avg('level'), 1);

        return match (true) {
            $difference >= 0.5 => "Days with an incident or failed deploy score {$difference} lower on average ({$rough->count()} rough days).",
            $difference <= -0.5 => 'You feel better on days with an incident or a failed deploy. Firefighting suits you.',
            default => 'Your mood does not seem to follow incidents or failed deploys.',
        };
    }

    /**
     * @return array<string, true>
     */
    private function roughDays(Carbon $since): array
    {
        $rough = [];
        foreach (Incident::where('started_at', '>=', $since)->pluck('started_at') as $at) {
            $rough[$at->toDateString()] = true;
        }
        foreach (Deployment::where('created_at', '>=', $since)->where(fn ($query) => $query->where('status', Deployment::FAILED)->orWhere('smoke_status', Deployment::SMOKE_FAILED))->pluck('created_at') as $at) {
            $rough[$at->toDateString()] = true;
        }

        return $rough;
    }
}
