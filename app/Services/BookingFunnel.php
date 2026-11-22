<?php

namespace App\Services;

use App\Models\AnalyticsSite;
use App\Models\Booking;
use App\Models\PointySite;
use Illuminate\Support\Collection;

/**
 * Pointy bookings next to the visitors of the website they came from. A Pointy site is matched to the analytics site with the same name or key, ignoring case and punctuation.
 */
class BookingFunnel
{
    public const DAYS = 7;

    /**
     * @param  Collection<int, PointySite>  $sites
     * @param  array<int, array{name: string, users: int|null, daily: array<int, int>}>|null  $analytics
     * @return array<int, array{name: string, bookings: int, daily_bookings: array<int, int>, users: int|null, daily_users: array<int, int>, rate: float|null}>
     */
    public function for(Collection $sites, ?array $analytics): array
    {
        $since = now()->subDays(self::DAYS - 1)->startOfDay();
        $bookings = Booking::active()->whereNotNull('pointy_site_id')->where('created_at', '>=', $since)->get(['pointy_site_id', 'created_at'])->groupBy('pointy_site_id');
        $rows = [];

        foreach ($sites as $site) {
            $mine = $bookings->get($site->id, collect());
            $daily = array_fill(0, self::DAYS, 0);
            foreach ($mine as $booking) {
                $index = self::DAYS - 1 - (int) $booking->created_at->copy()->startOfDay()->diffInDays(now()->startOfDay());
                if ($index >= 0 && $index < self::DAYS) {
                    $daily[$index]++;
                }
            }
            $names = [AnalyticsSite::normalize($site->name), AnalyticsSite::normalize($site->key)];
            $traffic = collect($analytics ?? [])->first(fn (array $row) => $row['users'] !== null && in_array(AnalyticsSite::normalize($row['name']), $names, true));
            $users = $traffic['users'] ?? null;

            $rows[] = [
                'name' => $site->name,
                'bookings' => $mine->count(),
                'daily_bookings' => $daily,
                'users' => $users,
                'daily_users' => $traffic['daily'] ?? [],
                'rate' => $users ? round($mine->count() / $users * 100, 1) : null,
            ];
        }

        return $rows;
    }
}
