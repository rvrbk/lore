<?php

namespace App\Services\Vault;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/**
 * Keeps the newest backup of each of the last N days and of each of the last M weeks, and deletes the rest.
 */
class VaultBackupRetention
{
    /** @return int how many files were deleted */
    public function prune(string $dir, int $keepDaily, int $keepWeekly): int
    {
        $byNewest = collect(File::glob($dir.'/vault-*.sealed'))
            ->mapWithKeys(fn (string $file) => [$file => $this->takenAt($file)])
            ->filter()
            ->sortDesc();

        $daily = $byNewest->unique(fn (CarbonImmutable $at) => $at->format('Y-m-d'))->take($keepDaily);
        $weekly = $byNewest->unique(fn (CarbonImmutable $at) => $at->format('o-W'))->take($keepWeekly);
        $keep = $daily->keys()->merge($weekly->keys());

        $delete = $byNewest->keys()->diff($keep);
        $delete->each(fn (string $file) => File::delete($file));

        return $delete->count();
    }

    private function takenAt(string $file): ?CarbonImmutable
    {
        return preg_match('/vault-(\d{8}-\d{6})\.sealed$/', $file, $m) ? CarbonImmutable::createFromFormat('Ymd-His', $m[1]) : null;
    }
}
