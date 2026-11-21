<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * The latest commit messages across Lore and every site with a deploy folder, newest first, for the dashboard ticker.
 */
class CommitLog
{
    /** Commits shown per repository, and in total. */
    public const PER_REPOSITORY = 5;

    public const TOTAL = 15;

    public function __construct(private Deployer $deployer) {}

    /**
     * @return array<int, array{site: string, hash: string, author: string, subject: string, at: int}>
     */
    public function latest(): array
    {
        return Cache::remember('dashboard.commits', now()->addMinutes(5), function (): array {
            $commits = [];
            foreach ($this->deployer->targets() as $target) {
                array_push($commits, ...$this->read($target['name'], $target['path']));
            }
            usort($commits, fn (array $a, array $b) => $b['at'] <=> $a['at']);

            return array_slice($commits, 0, self::TOTAL);
        });
    }

    /**
     * @return array<int, array{site: string, hash: string, author: string, subject: string, at: int}>
     */
    private function read(string $site, string $path): array
    {
        try {
            $result = Process::path($path)->timeout(10)->run([
                'git', '-c', 'safe.directory='.$path, 'log', '-n', (string) self::PER_REPOSITORY, '--format=%h%x09%ct%x09%an%x09%s',
            ]);
        } catch (Throwable) {
            return [];
        }

        if ($result->failed()) {
            return [];
        }

        $commits = [];
        foreach (array_filter(explode("\n", trim($result->output()))) as $line) {
            [$hash, $at, $author, $subject] = array_pad(explode("\t", $line, 4), 4, '');
            if ($subject !== '') {
                $commits[] = ['site' => $site, 'hash' => $hash, 'author' => $author, 'subject' => mb_substr($subject, 0, 140), 'at' => (int) $at];
            }
        }

        return $commits;
    }
}
