<?php

namespace App\Services;

use App\Models\Deployment;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

/**
 * Pulls the checked-out branch and runs the usual post-deploy steps. Only ever run by the scheduler
 * (as the user that owns the checkout), never by the web request itself.
 */
class Deployer
{
    private const STATUS_KEY = 'deploy.status';

    public function enabled(): bool
    {
        return (bool) config('services.deploy.enabled');
    }

    /** The branch the checkout is on, or null when detached. */
    public function branch(): ?string
    {
        $branch = trim($this->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD'])->output());

        return in_array($branch, ['', 'HEAD'], true) ? null : $branch;
    }

    /**
     * @return array{branch: string|null, commit: string, subject: string, behind: int, incoming: array<int, string>, checked_at: string, error: string|null}|null
     */
    public function status(): ?array
    {
        return Cache::get(self::STATUS_KEY);
    }

    /**
     * Fetch the branch from origin and remember how far behind the checkout is.
     *
     * @return array{branch: string|null, commit: string, subject: string, behind: int, incoming: array<int, string>, checked_at: string, error: string|null}
     */
    public function refreshStatus(): array
    {
        $branch = $this->branch();
        $status = [
            'branch' => $branch,
            'commit' => trim($this->run(['git', 'rev-parse', '--short', 'HEAD'])->output()),
            'subject' => trim($this->run(['git', 'log', '-1', '--format=%s'])->output()),
            'behind' => 0,
            'incoming' => [],
            'checked_at' => now()->toIso8601String(),
            'error' => null,
        ];

        if ($branch === null) {
            $status['error'] = 'The checkout is not on a branch (detached HEAD).';
        } else {
            $fetch = $this->run(['git', 'fetch', 'origin', $branch]);
            if ($fetch->failed()) {
                $status['error'] = 'Could not fetch from origin: '.trim($fetch->errorOutput());
            } else {
                $status['behind'] = (int) trim($this->run(['git', 'rev-list', '--count', 'HEAD..FETCH_HEAD'])->output());
                $status['incoming'] = array_values(array_filter(explode("\n", trim($this->run(['git', 'log', '--format=%h %s', '-n', '10', 'HEAD..FETCH_HEAD'])->output()))));
            }
        }

        Cache::forever(self::STATUS_KEY, $status);

        return $status;
    }

    public function perform(Deployment $deployment): void
    {
        $deployment->update(['status' => Deployment::RUNNING, 'started_at' => now()]);

        $log = '';
        $down = false;
        $ok = false;

        try {
            $branch = $this->branch();
            if ($branch === null) {
                $log .= "The checkout is not on a branch, so there is nothing to pull.\n";

                return;
            }

            $from = trim($this->run(['git', 'rev-parse', 'HEAD'])->output());
            $deployment->update(['branch' => $branch, 'from_commit' => $from]);

            $steps = [
                ['Back up the database', [PHP_BINARY, 'artisan', 'lore:backup']],
                ['Maintenance mode on', [PHP_BINARY, 'artisan', 'down']],
                ["Pull origin/{$branch}", ['git', 'pull', '--ff-only', 'origin', $branch]],
                ['Install PHP packages', array_values(array_filter(['composer', 'install', app()->isProduction() ? '--no-dev' : null, '--optimize-autoloader', '--no-interaction']))],
            ];

            foreach ($steps as [$title, $command]) {
                if ($title === 'Maintenance mode on') {
                    $down = true;
                }
                if (! $this->step($log, $title, $command)) {
                    return;
                }
            }

            $changed = explode("\n", trim($this->run(['git', 'diff', '--name-only', $from, 'HEAD'])->output()));
            $later = [];
            if (! is_dir(base_path('node_modules')) || in_array('package-lock.json', $changed, true)) {
                $later[] = ['Install JS packages', ['npm', 'ci', '--no-audit', '--no-fund']];
            }
            array_push($later,
                ['Build assets', ['npm', 'run', 'build']],
                ['Run migrations', [PHP_BINARY, 'artisan', 'migrate', '--force']],
                ['Cache config', [PHP_BINARY, 'artisan', 'config:cache']],
                ['Cache routes', [PHP_BINARY, 'artisan', 'route:cache']],
                ['Clear compiled views', [PHP_BINARY, 'artisan', 'view:clear']],
            );
            foreach ($later as [$title, $command]) {
                if (! $this->step($log, $title, $command)) {
                    return;
                }
            }

            $ok = true;
        } finally {
            if ($down) {
                $this->step($log, 'Maintenance mode off', [PHP_BINARY, 'artisan', 'up']);
            }
            $deployment->update([
                'status' => $ok ? Deployment::SUCCEEDED : Deployment::FAILED,
                'to_commit' => trim($this->run(['git', 'rev-parse', 'HEAD'])->output()) ?: null,
                'output' => $log,
                'finished_at' => now(),
            ]);
            $this->refreshStatus();
        }
    }

    /**
     * @param  array<int, string>  $command
     */
    private function step(string &$log, string $title, array $command): bool
    {
        $result = $this->run($command);
        $output = trim($result->output()."\n".$result->errorOutput());
        $log .= '## '.$title.($result->failed() ? ' (FAILED)' : '')."\n".mb_substr($output, -3000)."\n\n";

        return $result->successful();
    }

    /**
     * @param  array<int, string>  $command
     */
    private function run(array $command): ProcessResult
    {
        return Process::path(base_path())
            ->env(['PATH' => '/usr/local/bin:/usr/bin:/bin:'.getenv('PATH'), 'GIT_TERMINAL_PROMPT' => '0'])
            ->timeout(900)
            ->run($command);
    }
}
