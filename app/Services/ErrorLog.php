<?php

namespace App\Services;

use App\Models\Monitor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Error tracking without an outside service: reads the Laravel log of every site that has a deploy folder
 * and counts the ERROR-or-worse entries per day, grouping identical messages.
 */
class ErrorLog
{
    /** Only the end of each log file is read, so a huge log cannot stall a page. */
    public const TAIL_BYTES = 1_500_000;

    public const DAYS = 7;

    public const LEVELS = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    /**
     * @return array{daily: array<int, int>, total: int, last_day: int, recent: array<int, array{message: string, level: string, count: int, last_at: Carbon}>}|null Null when the site has no readable log.
     */
    public function forMonitor(Monitor $monitor): ?array
    {
        if ($monitor->deploy_path === null) {
            return null;
        }

        $summary = Cache::remember('error-log.v2.'.$monitor->id, now()->addMinutes(5), fn () => $this->summarize($this->entries($monitor->deploy_path)));
        if (! $summary) {
            return null;
        }

        foreach ($summary['recent'] as &$group) {
            $group['last_at'] = Carbon::createFromTimestamp($group['last_at']);
        }

        return $summary;
    }

    /**
     * Error entries of a site within a window, oldest first, for the incident timeline.
     *
     * @return array<int, array{at: Carbon, level: string, message: string}>
     */
    public function between(Monitor $monitor, Carbon $from, Carbon $to): array
    {
        if ($monitor->deploy_path === null) {
            return [];
        }

        return array_values(array_filter($this->entries($monitor->deploy_path), fn (array $entry) => $entry['at']->between($from, $to)));
    }

    /**
     * @return array<int, array{at: Carbon, level: string, message: string}>
     */
    public function entries(string $path): array
    {
        $files = glob(rtrim($path, '/').'/storage/logs/laravel*.log') ?: [];
        usort($files, fn (string $a, string $b) => (@filemtime($b) ?: 0) <=> (@filemtime($a) ?: 0));
        $since = now()->subDays(self::DAYS - 1)->startOfDay();
        $entries = [];

        foreach (array_slice($files, 0, 3) as $file) {
            if (! is_readable($file) || (@filemtime($file) ?: 0) < $since->timestamp) {
                continue;
            }
            try {
                $size = filesize($file);
                $handle = fopen($file, 'rb');
                if ($handle === false) {
                    continue;
                }
                if ($size > self::TAIL_BYTES) {
                    fseek($handle, -self::TAIL_BYTES, SEEK_END);
                }
                $text = (string) stream_get_contents($handle);
                fclose($handle);
            } catch (Throwable) {
                continue;
            }

            preg_match_all('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\][^\n]*?\.(ERROR|CRITICAL|ALERT|EMERGENCY): ([^\n]*)/m', $text, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $at = Carbon::parse($match[1], config('app.timezone'));
                if ($at >= $since) {
                    $entries[] = ['at' => $at, 'level' => $match[2], 'message' => mb_substr(trim($match[3]), 0, 200)];
                }
            }
        }

        usort($entries, fn (array $a, array $b) => $a['at'] <=> $b['at']);

        return $entries;
    }

    /**
     * @param  array<int, array{at: Carbon, level: string, message: string}>  $entries
     * @return array{daily: array<int, int>, total: int, last_day: int, recent: array<int, array{message: string, level: string, count: int, last_at: Carbon}>}|false
     */
    private function summarize(array $entries): array|false
    {
        if ($entries === []) {
            return false;
        }

        $daily = array_fill(0, self::DAYS, 0);
        $groups = [];
        foreach ($entries as $entry) {
            $index = self::DAYS - 1 - (int) $entry['at']->copy()->startOfDay()->diffInDays(now()->startOfDay());
            if ($index >= 0 && $index < self::DAYS) {
                $daily[$index]++;
            }
            $key = preg_replace('/\d+/', '#', $entry['message']);
            $groups[$key] ??= ['message' => $entry['message'], 'level' => $entry['level'], 'count' => 0, 'last_at' => 0];
            $groups[$key]['count']++;
            $groups[$key]['last_at'] = $entry['at']->timestamp;
        }
        usort($groups, fn (array $a, array $b) => $b['last_at'] <=> $a['last_at']);

        return ['daily' => $daily, 'total' => count($entries), 'last_day' => count(array_filter($entries, fn (array $entry) => $entry['at'] >= now()->subDay())), 'recent' => array_slice($groups, 0, 8)];
    }
}
