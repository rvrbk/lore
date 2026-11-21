<?php

namespace App\Services;

use App\Models\Deployment;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Task;

/**
 * Turns a detected problem into an incident: a card on the Kanban board, a Telegram message and a banner in the app.
 * One incident stays open per kind and site until it is resolved, so a problem that persists does not repeat itself.
 */
class IncidentReporter
{
    /** A failed deploy check or outage this soon after a deploy is blamed on that deploy. */
    public const DEPLOY_BLAME_MINUTES = 30;

    public function __construct(private Telegram $telegram) {}

    /**
     * @param  bool  $notify  Set to false when the caller already sends its own Telegram message.
     */
    public function open(string $kind, string $title, ?Monitor $monitor = null, ?string $details = null, ?Deployment $deployment = null, bool $notify = true): Incident
    {
        $existing = Incident::open()->where('kind', $kind)->where('monitor_id', $monitor?->id)->first();
        if ($existing) {
            return $existing;
        }

        $deployment ??= $monitor ? $this->recentDeployment($monitor) : null;

        $task = Task::createAtBottom([
            'title' => $title,
            'description' => trim(($details ?? '')."\nOpened ".now()->format('D j M H:i').' · '.route('incidents.index')),
            'status' => 'todo',
            'priority' => 'high',
            'due_on' => today(),
        ]);
        $task->syncTagNames(array_filter(['incident', $monitor?->name]));

        $incident = Incident::create([
            'monitor_id' => $monitor?->id,
            'deployment_id' => $deployment?->id,
            'task_id' => $task->id,
            'kind' => $kind,
            'title' => $title,
            'details' => $details,
            'started_at' => now(),
        ]);

        if ($notify) {
            $lines = ['🐞 <b>'.e($title).'</b>'];
            if ($details) {
                $lines[] = e($details);
            }
            if ($deployment) {
                $lines[] = 'Follows deploy #'.$deployment->id.' ('.e($deployment->site ?? 'lore').').';
            }
            $lines[] = '<a href="'.e(route('incidents.index')).'">Open incidents</a>';
            $this->telegram->send(implode("\n", $lines));
        }

        return $incident;
    }

    /**
     * Close every open incident of this kind for the site.
     */
    public function resolve(string $kind, ?Monitor $monitor = null, bool $notify = true): void
    {
        foreach (Incident::open()->where('kind', $kind)->where('monitor_id', $monitor?->id)->get() as $incident) {
            $incident->update(['resolved_at' => now()]);

            if ($incident->task) {
                $incident->task->update(['description' => trim(($incident->task->description ?? '')."\nResolved after ".$incident->duration().'.')]);
            }
            if ($notify) {
                $this->telegram->send('✅ <b>Resolved:</b> '.e($incident->title)."\nAfter ".e($incident->duration()).'.');
            }
        }
    }

    private function recentDeployment(Monitor $monitor): ?Deployment
    {
        if ($monitor->deploy_path === null) {
            return null;
        }

        return Deployment::where('path', $monitor->deploy_path)
            ->where('finished_at', '>=', now()->subMinutes(self::DEPLOY_BLAME_MINUTES))
            ->latest('finished_at')->first();
    }
}
