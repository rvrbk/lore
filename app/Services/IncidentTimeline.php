<?php

namespace App\Services;

use App\Models\Deployment;
use App\Models\Incident;
use App\Models\MonitorCheck;
use App\Models\Page;
use Carbon\Carbon;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Everything Lore knows that happened around an incident, in order: deploys, commits, failing checks, error log entries and page edits.
 */
class IncidentTimeline
{
    /** How long before the incident started to look back. */
    public const LOOKBACK_HOURS = 3;

    public const LIMIT = 40;

    public function __construct(private ErrorLog $errors) {}

    /**
     * @return array<int, array{at: Carbon, icon: string, text: string, url: string|null}>
     */
    public function for(Incident $incident): array
    {
        $from = $incident->started_at->copy()->subHours(self::LOOKBACK_HOURS);
        $to = ($incident->resolved_at ?? now())->copy();
        $events = [['at' => $incident->started_at->copy(), 'icon' => '🐞', 'text' => 'Incident opened: '.$incident->title, 'url' => null]];

        if ($incident->resolved_at !== null) {
            $events[] = ['at' => $incident->resolved_at->copy(), 'icon' => '✅', 'text' => 'Resolved after '.$incident->duration(), 'url' => null];
        }

        $monitor = $incident->monitor;
        if ($monitor !== null) {
            foreach ($this->failures($monitor->id, $from, $to) as $event) {
                $events[] = $event;
            }

            if ($monitor->deploy_path !== null) {
                $deployments = Deployment::where('path', $monitor->deploy_path)->whereBetween('finished_at', [$from, $to])->orderBy('finished_at')->get();
                foreach ($deployments as $deployment) {
                    $events[] = [
                        'at' => $deployment->finished_at->copy(),
                        'icon' => '🚀',
                        'text' => 'Deploy #'.$deployment->id.' '.$deployment->status.($deployment->to_commit ? ' ('.substr($deployment->to_commit, 0, 7).')' : '').($deployment->smoke_status === Deployment::SMOKE_FAILED ? ', site did not answer afterwards' : ''),
                        'url' => route('deploy.index'),
                    ];
                }
                foreach ($this->commits($monitor->deploy_path, $from, $to) as $event) {
                    $events[] = $event;
                }
            }

            foreach ($this->errors->between($monitor, $from, $to) as $entry) {
                $events[] = ['at' => $entry['at'], 'icon' => '⚠️', 'text' => $entry['level'].': '.$entry['message'], 'url' => null];
            }

            $tag = $monitor->tagName();
            if ($tag !== '') {
                foreach (Page::withTag($tag)->whereBetween('updated_at', [$from, $to])->get() as $page) {
                    $events[] = ['at' => $page->updated_at->copy(), 'icon' => '📝', 'text' => 'Page edited: '.$page->title, 'url' => route('pages.show', $page)];
                }
            }
        }

        usort($events, fn (array $a, array $b) => $a['at'] <=> $b['at']);

        return array_slice($events, 0, self::LIMIT);
    }

    /**
     * The first failing check of each outage and the check that recovered it.
     *
     * @return array<int, array{at: Carbon, icon: string, text: string, url: string|null}>
     */
    private function failures(int $monitorId, Carbon $from, Carbon $to): array
    {
        $events = [];
        $down = false;
        foreach (MonitorCheck::where('monitor_id', $monitorId)->whereBetween('checked_at', [$from, $to])->orderBy('checked_at')->get() as $check) {
            if (! $check->is_up && ! $down) {
                $down = true;
                $events[] = ['at' => $check->checked_at->copy(), 'icon' => '🔴', 'text' => 'First failed check'.($check->status_code ? ' (HTTP '.$check->status_code.')' : '').($check->error ? ': '.mb_substr($check->error, 0, 120) : ''), 'url' => null];
            } elseif ($check->is_up && $down) {
                $down = false;
                $events[] = ['at' => $check->checked_at->copy(), 'icon' => '🟢', 'text' => 'Check passed again', 'url' => null];
            }
        }

        return $events;
    }

    /**
     * @return array<int, array{at: Carbon, icon: string, text: string, url: string|null}>
     */
    private function commits(string $path, Carbon $from, Carbon $to): array
    {
        try {
            $result = Process::path($path)->timeout(10)->run([
                'git', '-c', 'safe.directory='.$path, 'log', '--since='.$from->timestamp, '--until='.$to->timestamp, '-n', '10', '--format=%h%x09%ct%x09%an%x09%s',
            ]);
        } catch (Throwable) {
            return [];
        }
        if ($result->failed()) {
            return [];
        }

        $events = [];
        foreach (array_filter(explode("\n", trim($result->output()))) as $line) {
            [$hash, $at, $author, $subject] = array_pad(explode("\t", $line, 4), 4, '');
            if ($subject !== '') {
                $events[] = ['at' => Carbon::createFromTimestamp((int) $at), 'icon' => '🔧', 'text' => "Commit {$hash} by {$author}: ".mb_substr($subject, 0, 100), 'url' => null];
            }
        }

        return $events;
    }
}
