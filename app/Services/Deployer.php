<?php

namespace App\Services;

use App\Models\Deployment;
use App\Models\Monitor;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

/**
 * Pulls the checked-out branch of Lore and of every registered site that has a server path, then runs the
 * usual post-deploy steps for whatever the site is (Laravel, Node build). Only ever run by the scheduler
 * as the user that owns the checkouts, never by a web request.
 */
class Deployer
{
    /** Site paths must sit directly under this directory. */
    public const ROOT = '/var/www';

    public function enabled(): bool
    {
        return (bool) config('services.deploy.enabled');
    }

    /**
     * Lore itself plus every monitored site with a deploy path.
     *
     * @return array<int, array{name: string, path: string}>
     */
    public function targets(): array
    {
        $targets = [['name' => 'lore', 'path' => base_path()]];
        foreach (Monitor::whereNotNull('deploy_path')->orderBy('name')->get(['name', 'deploy_path']) as $monitor) {
            if (! in_array($monitor->deploy_path, array_column($targets, 'path'), true)) {
                $targets[] = ['name' => $monitor->name, 'path' => $monitor->deploy_path];
            }
        }

        return $targets;
    }

    /** The branch a checkout is on, or null when detached or not a checkout. */
    public function branch(string $path): ?string
    {
        $branch = trim($this->run($path, ['git', 'rev-parse', '--abbrev-ref', 'HEAD'])->output());

        return in_array($branch, ['', 'HEAD'], true) ? null : $branch;
    }

    /**
     * @return array{branch: string|null, commit: string, subject: string, behind: int, incoming: array<int, string>, checked_at: string, error: string|null}|null
     */
    public function status(string $path): ?array
    {
        return Cache::get($this->statusKey($path));
    }

    /**
     * Fetch the branch from origin and remember how far behind the checkout is.
     *
     * @return array{branch: string|null, commit: string, subject: string, behind: int, incoming: array<int, string>, checked_at: string, error: string|null}
     */
    public function refreshStatus(string $path): array
    {
        $branch = $this->branch($path);
        $status = [
            'branch' => $branch,
            'commit' => trim($this->run($path, ['git', 'rev-parse', '--short', 'HEAD'])->output()),
            'subject' => trim($this->run($path, ['git', 'log', '-1', '--format=%s'])->output()),
            'behind' => 0,
            'incoming' => [],
            'checked_at' => now()->toIso8601String(),
            'error' => null,
        ];

        if ($branch === null) {
            $status['error'] = 'Not a git checkout on a branch.';
        } else {
            $fetch = $this->run($path, ['git', 'fetch', 'origin', $branch]);
            if ($fetch->failed()) {
                $status['error'] = 'Could not fetch from origin: '.trim($fetch->errorOutput());
            } else {
                $status['behind'] = (int) trim($this->run($path, ['git', 'rev-list', '--count', 'HEAD..FETCH_HEAD'])->output());
                $status['incoming'] = array_values(array_filter(explode("\n", trim($this->run($path, ['git', 'log', '--format=%h %s', '-n', '10', 'HEAD..FETCH_HEAD'])->output()))));
            }
        }

        Cache::forever($this->statusKey($path), $status);

        return $status;
    }

    public function perform(Deployment $deployment): void
    {
        $path = $deployment->path ?: base_path();
        $deployment->update(['status' => Deployment::RUNNING, 'started_at' => now(), 'path' => $path]);

        $log = '';
        $down = false;
        $ok = false;
        $laravel = is_file($path.'/artisan');

        try {
            $branch = $this->branch($path);
            if ($branch === null) {
                $log .= "{$path} is not a git checkout on a branch, so there is nothing to pull.\n";

                return;
            }

            $from = trim($this->run($path, ['git', 'rev-parse', 'HEAD'])->output());
            $deployment->update(['branch' => $branch, 'from_commit' => $from]);

            $steps = [];
            if ($path === base_path()) {
                $steps[] = ['Back up the database', ['php', 'artisan', 'lore:backup']];
            }
            // Only take a Laravel site offline when the incoming commits carry migrations; otherwise it keeps serving while it builds.
            $this->run($path, ['git', 'fetch', 'origin', $branch]);
            $incoming = explode("\n", trim($this->run($path, ['git', 'diff', '--name-only', 'HEAD', 'FETCH_HEAD'])->output()));
            if ($laravel && collect($incoming)->contains(fn (string $file) => str_starts_with($file, 'database/migrations/'))) {
                $steps[] = ['Maintenance mode on', ['php', 'artisan', 'down']];
            }
            $steps[] = ["Pull origin/{$branch}", ['git', 'pull', '--ff-only', 'origin', $branch]];

            foreach ($steps as [$title, $command]) {
                if ($title === 'Maintenance mode on') {
                    $down = true;
                }
                if (! $this->step($log, $path, $title, $command)) {
                    return;
                }
            }

            $changed = explode("\n", trim($this->run($path, ['git', 'diff', '--name-only', $from, 'HEAD'])->output()));
            $later = [];
            if (is_file($path.'/composer.json')) {
                $later[] = ['Install PHP packages', array_values(array_filter(['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction']))];
            }
            if (is_file($path.'/package.json')) {
                if (! is_dir($path.'/node_modules') || in_array('package-lock.json', $changed, true)) {
                    $later[] = ['Install JS packages', ['npm', 'ci', '--no-audit', '--no-fund']];
                }
                if (isset((json_decode((string) file_get_contents($path.'/package.json'), true)['scripts'] ?? [])['build'])) {
                    $later[] = ['Build assets', ['npm', 'run', 'build']];
                }
            }
            if ($laravel) {
                array_push($later,
                    ['Run migrations', ['php', 'artisan', 'migrate', '--force']],
                    ['Cache config', ['php', 'artisan', 'config:cache']],
                    ['Cache routes', ['php', 'artisan', 'route:cache']],
                    ['Clear compiled views', ['php', 'artisan', 'view:clear']],
                );
            }
            foreach ($later as [$title, $command]) {
                if (! $this->step($log, $path, $title, $command)) {
                    return;
                }
            }

            $ok = true;
        } finally {
            if ($down) {
                $this->step($log, $path, 'Maintenance mode off', ['php', 'artisan', 'up']);
            }
            $deployment->update([
                'status' => $ok ? Deployment::SUCCEEDED : Deployment::FAILED,
                'to_commit' => trim($this->run($path, ['git', 'rev-parse', 'HEAD'])->output()) ?: null,
                'output' => $log,
                'finished_at' => now(),
            ]);
            $this->refreshStatus($path);
        }
    }

    private function statusKey(string $path): string
    {
        return 'deploy.status.'.md5($path);
    }

    /**
     * @param  array<int, string>  $command
     */
    private function step(string &$log, string $path, string $title, array $command): bool
    {
        $result = $this->run($path, $command);
        $output = trim($result->output()."\n".$result->errorOutput());
        $log .= '## '.$title.($result->failed() ? ' (FAILED)' : '')."\n".mb_substr($output, -3000)."\n\n";

        return $result->successful();
    }

    /**
     * @param  array<int, string>  $command
     */
    private function run(string $path, array $command): ProcessResult
    {
        return Process::path($path)
            ->env(['PATH' => '/usr/local/bin:/usr/bin:/bin:'.getenv('PATH'), 'GIT_TERMINAL_PROMPT' => '0'])
            ->timeout(900)
            ->run($command);
    }
}
