<?php

namespace App\Console\Commands;

use App\Models\HeatClick;
use App\Models\Monitor;
use App\Services\PageScreenshot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('heatmaps:screenshots {--force : Retake screenshots that are still fresh}')]
#[Description('Screenshot the most clicked pages of every site so heatmaps can be drawn on them')]
class CaptureHeatmapScreenshots extends Command
{
    public const PAGES_PER_DEVICE = 5;

    public function handle(PageScreenshot $screenshots): int
    {
        if ($screenshots->binary() === null) {
            $this->error('Chromium is not installed (or HEATMAP_CHROMIUM is wrong).');

            return self::FAILURE;
        }

        $taken = 0;
        foreach (Monitor::orderBy('name')->get() as $monitor) {
            foreach (array_keys(PageScreenshot::WIDTHS) as $device) {
                $pages = HeatClick::where('monitor_id', $monitor->id)->where('device', $device)->where('created_at', '>=', now()->subDays(30))
                    ->selectRaw('path, max(y) as deepest')->groupBy('path')->orderByRaw('count(*) desc')->limit(self::PAGES_PER_DEVICE)->get();

                foreach ($pages as $page) {
                    if (! $this->option('force') && $screenshots->isFresh($monitor, $page->path, $device)) {
                        continue;
                    }
                    $ok = $screenshots->capture($monitor, $page->path, $device, $screenshots->heightFor((int) $page->deepest));
                    $ok ? $taken++ : $this->warn("{$monitor->name} {$device} {$page->path}: no screenshot.");
                }
            }
        }
        $this->info("Took {$taken} screenshot(s).");

        return self::SUCCESS;
    }
}
