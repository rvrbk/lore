<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Reminder;
use App\Models\ServerMetric;
use App\Models\Task;
use App\Services\GoogleAnalytics;
use App\Services\Telegram;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lore:digest {--print : Show the message instead of sending it to Telegram}')]
#[Description('Send the morning digest to Telegram: what is due, what broke overnight and yesterday\'s traffic')]
class SendMorningDigest extends Command
{
    public function handle(Telegram $telegram, GoogleAnalytics $analytics): int
    {
        $message = $this->message($analytics);

        if ($this->option('print')) {
            $this->line(strip_tags($message));

            return self::SUCCESS;
        }

        if (! $telegram->isConfigured()) {
            $this->warn('Telegram is not configured, so there is nobody to send the digest to.');

            return self::SUCCESS;
        }

        return $telegram->send($message) ? self::SUCCESS : self::FAILURE;
    }

    private function message(GoogleAnalytics $analytics): string
    {
        $sections = ['☀️ <b>Good morning</b> · '.now()->format('l j F')];

        $sections[] = $this->health();
        $sections[] = $this->reminders();
        $sections[] = $this->tasks();
        $sections[] = $this->traffic($analytics);
        $sections[] = $this->expiries();

        return implode("\n\n", array_filter($sections));
    }

    private function health(): string
    {
        $lines = [];
        $open = Incident::open()->orderBy('started_at')->get();
        $overnight = Incident::where('started_at', '>=', now()->subDay())->count();

        if ($open->isEmpty()) {
            $lines[] = '🟢 All sites up'.($overnight ? " · {$overnight} incident".($overnight === 1 ? '' : 's').' in the last 24 hours, all resolved' : ', no incidents overnight').'.';
        } else {
            $lines[] = '🔴 <b>'.$open->count().' open incident'.($open->count() === 1 ? '' : 's').'</b>';
            foreach ($open as $incident) {
                $lines[] = '• '.e($incident->title).' (for '.e($incident->duration()).')';
            }
        }

        $disk = ServerMetric::latest('recorded_at')->first();
        if ($disk?->disk_used_percent !== null) {
            $lines[] = 'Disk '.round($disk->disk_used_percent).'% used'.($disk->failed_jobs ? ' · '.$disk->failed_jobs.' failed job(s)' : '').'.';
        }

        return implode("\n", $lines);
    }

    private function reminders(): string
    {
        $due = Reminder::open()->where('due_at', '<=', now()->endOfDay())->orderBy('due_at')->limit(10)->get();
        if ($due->isEmpty()) {
            return '⏰ No reminders due today.';
        }

        return "⏰ <b>Reminders</b>\n".$due->map(fn (Reminder $r) => '• '.e($r->title).' ('.($r->due_at->isToday() ? $r->due_at->format('H:i') : 'overdue since '.$r->due_at->format('j M')).')')->implode("\n");
    }

    private function tasks(): string
    {
        $open = Task::where('status', '!=', 'done');
        $attention = (clone $open)->whereNotNull('due_on')->whereDate('due_on', '<=', today())->orderBy('due_on')->limit(10)->get();
        $inProgress = (clone $open)->where('status', 'in_progress')->count();

        $lines = ['📋 <b>Board</b> · '.$inProgress.' in progress'];
        foreach ($attention as $task) {
            $lines[] = '• '.e($task->title).' ('.($task->isOverdue() ? 'overdue since '.$task->due_on->format('j M') : 'due today').')';
        }

        return implode("\n", $lines);
    }

    private function traffic(GoogleAnalytics $analytics): ?string
    {
        $summary = $analytics->summary();
        if (! $summary) {
            return null;
        }

        $lines = ['📈 <b>Yesterday\'s visitors</b>'];
        foreach ($summary as $site) {
            if ($site['users'] !== null) {
                $lines[] = '• '.e($site['name']).': '.number_format($site['daily'][5] ?? 0);
            }
        }

        return count($lines) > 1 ? implode("\n", $lines) : null;
    }

    private function expiries(): ?string
    {
        $soon = Monitor::all()->flatMap(fn (Monitor $monitor) => array_filter([
            $monitor->ssl_expires_at ? ['label' => "{$monitor->name} SSL", 'at' => $monitor->ssl_expires_at] : null,
            $monitor->domain_expires_at ? ['label' => "{$monitor->name} domain", 'at' => $monitor->domain_expires_at] : null,
        ]))->filter(fn (array $item) => $item['at']->lte(now()->addDays(30)))->sortBy('at');

        if ($soon->isEmpty()) {
            return null;
        }

        return "🔐 <b>Expiring within 30 days</b>\n".$soon->map(fn (array $item) => '• '.e($item['label']).' on '.$item['at']->format('j M'))->implode("\n");
    }
}
