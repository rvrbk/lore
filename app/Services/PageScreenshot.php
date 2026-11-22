<?php

namespace App\Services;

use App\Models\Monitor;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Takes the screenshots that heatmaps are drawn on, with headless Chromium on the server. The window is made
 * as tall as the clicks reach, so one capture covers the part of the page that was used.
 */
class PageScreenshot
{
    public const WIDTHS = ['desktop' => 1280, 'mobile' => 390];

    public const MIN_HEIGHT = 1200;

    public const MAX_HEIGHT = 12000;

    public const STALE_AFTER_DAYS = 7;

    public const TIMEOUT_SECONDS = 60;

    private const MOBILE_USER_AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    public function binary(): ?string
    {
        $configured = config('services.heatmap.chromium');
        if (filled($configured)) {
            return is_executable($configured) ? $configured : null;
        }

        return (new ExecutableFinder)->find('chromium') ?? (new ExecutableFinder)->find('chromium-browser') ?? (new ExecutableFinder)->find('google-chrome');
    }

    public function relativePath(Monitor $monitor, string $path, string $device): string
    {
        return "screenshots/{$monitor->id}/{$device}-".sha1($path).'.png';
    }

    public function exists(Monitor $monitor, string $path, string $device): bool
    {
        return Storage::disk('local')->exists($this->relativePath($monitor, $path, $device));
    }

    /** True when a screenshot exists and is younger than STALE_AFTER_DAYS. */
    public function isFresh(Monitor $monitor, string $path, string $device): bool
    {
        $file = $this->relativePath($monitor, $path, $device);

        return Storage::disk('local')->exists($file)
            && Storage::disk('local')->lastModified($file) > now()->subDays(self::STALE_AFTER_DAYS)->getTimestamp();
    }

    /** Pixel height of the stored screenshot, or null when there is none. */
    public function height(Monitor $monitor, string $path, string $device): ?int
    {
        if (! $this->exists($monitor, $path, $device)) {
            return null;
        }
        $size = @getimagesize(Storage::disk('local')->path($this->relativePath($monitor, $path, $device)));

        return $size === false ? null : $size[1];
    }

    public function heightFor(int $deepestClickY): int
    {
        return max(self::MIN_HEIGHT, min(self::MAX_HEIGHT, $deepestClickY + 600));
    }

    /**
     * Capture the page and store it. Returns false when Chromium is missing, the path is not a plain URL path,
     * or the browser produced no image.
     */
    public function capture(Monitor $monitor, string $path, string $device, int $height): bool
    {
        $binary = $this->binary();
        if ($binary === null || ! isset(self::WIDTHS[$device]) || ! preg_match('#^/[A-Za-z0-9\-._~/%]*$#', $path)) {
            return false;
        }

        $directory = sys_get_temp_dir().'/lore-chromium';
        @mkdir($directory, 0775, true);
        $temporary = $directory.'/'.bin2hex(random_bytes(6)).'.png';

        $command = [
            $binary, '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--hide-scrollbars',
            '--force-device-scale-factor=1', '--virtual-time-budget=10000', '--user-data-dir='.$directory.'/profile',
            '--window-size='.self::WIDTHS[$device].','.$height,
            '--screenshot='.$temporary,
        ];
        if ($device === 'mobile') {
            $command[] = '--user-agent='.self::MOBILE_USER_AGENT;
        }
        $command[] = rtrim($monitor->url, '/').$path;

        try {
            Process::timeout(self::TIMEOUT_SECONDS)->run($command);
            if (! is_file($temporary) || filesize($temporary) === 0) {
                return false;
            }

            return Storage::disk('local')->put($this->relativePath($monitor, $path, $device), (string) file_get_contents($temporary));
        } finally {
            @unlink($temporary);
        }
    }
}
