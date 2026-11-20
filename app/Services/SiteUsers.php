<?php

namespace App\Services;

use App\Models\Monitor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

/**
 * Counts the rows in the users table of every registered site that has a server folder. Each count is read by
 * booting that site's own app, so its own credentials are used and nothing is copied. Only ever run by the
 * scheduler as the user that owns the checkouts, never by a web request.
 */
class SiteUsers
{
    private const CACHE_KEY = 'site-users.counts';

    private const MARKER = 'LORE_USER_COUNT=';

    /**
     * Count the users of every site and remember the result for the dashboard.
     *
     * @return array<int, array{name: string, users: int|null}>
     */
    public function refresh(): array
    {
        $counts = [];
        foreach (Monitor::whereNotNull('deploy_path')->orderBy('name')->get(['name', 'deploy_path']) as $monitor) {
            $counts[] = ['name' => $monitor->name, 'users' => $this->countAt($monitor->deploy_path)];
        }

        Cache::forever(self::CACHE_KEY, $counts);

        return $counts;
    }

    /**
     * @return array<int, array{name: string, users: int|null}>|null
     */
    public function counts(): ?array
    {
        $counts = Cache::get(self::CACHE_KEY);

        return $counts ? $counts : null;
    }

    /** The number of users of the Laravel app in the folder, or null when it is not a Laravel app or the count failed. */
    public function countAt(string $path): ?int
    {
        if (! str_starts_with($path, Deployer::ROOT.'/')) {
            return null;
        }

        $script = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; '
            .'$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); '
            .'echo "'.self::MARKER.'".Illuminate\Support\Facades\DB::table("users")->count();';

        $result = Process::path($path)->timeout(30)->run(['php', '-r', $script]);

        return $result->successful() && preg_match('/'.self::MARKER.'(\d+)/', $result->output(), $match) ? (int) $match[1] : null;
    }
}
