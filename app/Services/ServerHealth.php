<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\ServerMetric;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Samples the machine Lore runs on (disk, memory, load, queue) and raises an incident before the disk fills up
 * or memory stays exhausted.
 */
class ServerHealth
{
    public const DISK_ALERT_PERCENT = 85;

    public const DISK_CLEAR_PERCENT = 80;

    /** A disk that is on course to fill within this many days is flagged early, once it is at least DISK_TREND_FROM_PERCENT full. */
    public const DISK_FULL_WITHIN_DAYS = 7;

    public const DISK_TREND_FROM_PERCENT = 60;

    public const MEMORY_ALERT_PERCENT = 92;

    public const MEMORY_CLEAR_PERCENT = 85;

    /** Memory must stay above the alert level for this many samples in a row. */
    public const MEMORY_ALERT_SAMPLES = 3;

    public function __construct(private IncidentReporter $incidents) {}

    public function measure(): ServerMetric
    {
        $metric = ServerMetric::create($this->readings() + ['recorded_at' => now()]);
        ServerMetric::where('recorded_at', '<', now()->subDays(ServerMetric::KEEP_HISTORY_DAYS))->delete();

        $this->evaluate($metric);

        return $metric;
    }

    /**
     * @return array{disk_used_percent: float|null, disk_free_bytes: int|null, memory_used_percent: float|null, load_average: float|null, queue_depth: int, failed_jobs: int}
     */
    public function readings(): array
    {
        $path = is_dir('/var/www') ? '/var/www' : base_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        $memory = null;
        if (is_readable('/proc/meminfo')) {
            preg_match('/MemTotal:\s+(\d+)/', (string) file_get_contents('/proc/meminfo'), $totalMatch);
            preg_match('/MemAvailable:\s+(\d+)/', (string) file_get_contents('/proc/meminfo'), $availableMatch);
            if (! empty($totalMatch[1]) && isset($availableMatch[1])) {
                $memory = round((1 - $availableMatch[1] / $totalMatch[1]) * 100, 2);
            }
        }

        return [
            'disk_used_percent' => $total ? round((1 - $free / $total) * 100, 2) : null,
            'disk_free_bytes' => $free !== false ? (int) $free : null,
            'memory_used_percent' => $memory,
            'load_average' => function_exists('sys_getloadavg') ? round(sys_getloadavg()[0] ?? 0, 2) : null,
            'queue_depth' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
            'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
        ];
    }

    public function evaluate(ServerMetric $latest): void
    {
        $this->evaluateDisk($latest);
        $this->evaluateMemory($latest);
    }

    /** Days until the disk is full if the last week's growth continues, or null when it is not growing. */
    public function daysUntilDiskFull(): ?int
    {
        $samples = ServerMetric::where('recorded_at', '>=', now()->subDays(7))->whereNotNull('disk_used_percent')->orderBy('recorded_at')->get(['recorded_at', 'disk_used_percent']);
        if ($samples->count() < 2) {
            return null;
        }

        $first = $samples->first();
        $last = $samples->last();
        $days = $first->recorded_at->diffInSeconds($last->recorded_at) / 86400;
        $growthPerDay = $days > 0 ? ($last->disk_used_percent - $first->disk_used_percent) / $days : 0;

        return $growthPerDay > 0.05 ? (int) floor((100 - $last->disk_used_percent) / $growthPerDay) : null;
    }

    /**
     * Recent samples for the charts on the Server health page, oldest first.
     *
     * @return Collection<int, ServerMetric>
     */
    public function recent(int $hours = 48): Collection
    {
        return ServerMetric::where('recorded_at', '>=', now()->subHours($hours))->orderBy('recorded_at')->get();
    }

    private function evaluateDisk(ServerMetric $latest): void
    {
        $used = $latest->disk_used_percent;
        if ($used === null) {
            return;
        }

        $daysLeft = $this->daysUntilDiskFull();
        $filling = $used >= self::DISK_TREND_FROM_PERCENT && $daysLeft !== null && $daysLeft <= self::DISK_FULL_WITHIN_DAYS;

        if ($used >= self::DISK_ALERT_PERCENT || $filling) {
            $details = $used.'% used, '.$this->humanBytes((int) $latest->disk_free_bytes).' free'.($daysLeft !== null && $filling ? "; full in about {$daysLeft} day".($daysLeft === 1 ? '' : 's').' at this rate' : '').'.';
            $this->incidents->open(Incident::DISK_FULL, $used >= self::DISK_ALERT_PERCENT ? 'Server disk is '.round($used).'% full' : 'Server disk is filling up fast', null, $details);
        } elseif ($used < self::DISK_CLEAR_PERCENT && ! $filling) {
            $this->incidents->resolve(Incident::DISK_FULL);
        }
    }

    private function evaluateMemory(ServerMetric $latest): void
    {
        if ($latest->memory_used_percent === null) {
            return;
        }

        $recent = ServerMetric::whereNotNull('memory_used_percent')->orderByDesc('id')->limit(self::MEMORY_ALERT_SAMPLES)->pluck('memory_used_percent');

        if ($recent->count() === self::MEMORY_ALERT_SAMPLES && $recent->every(fn (float $percent) => $percent >= self::MEMORY_ALERT_PERCENT)) {
            $this->incidents->open(Incident::MEMORY_HIGH, 'Server memory is '.round($latest->memory_used_percent).'% used', null, 'Above '.self::MEMORY_ALERT_PERCENT.'% for the last '.(self::MEMORY_ALERT_SAMPLES * 5).' minutes.');
        } elseif ($latest->memory_used_percent < self::MEMORY_CLEAR_PERCENT) {
            $this->incidents->resolve(Incident::MEMORY_HIGH);
        }
    }

    private function humanBytes(int $bytes): string
    {
        return $bytes >= 1024 ** 3 ? round($bytes / 1024 ** 3, 1).' GB' : round($bytes / 1024 ** 2).' MB';
    }
}
