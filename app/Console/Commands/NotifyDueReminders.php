<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Services\Telegram;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

#[Signature('reminders:notify')]
#[Description('Send a desktop notification for every reminder that has come due')]
class NotifyDueReminders extends Command
{
    public function handle(Telegram $telegram): int
    {
        $reminders = Reminder::due()->whereNull('notified_at')->orderBy('due_at')->get();

        foreach ($reminders as $reminder) {
            $result = Process::run([
                'notify-send', '--app-name=Lore', '--urgency=normal',
                $reminder->title,
                $reminder->notes ?: 'Due '.$reminder->due_at->format('D j M H:i'),
            ]);

            if ($result->failed()) {
                Log::warning('Reminder notification failed', ['reminder' => $reminder->id, 'error' => $result->errorOutput()]);
            }
            // A Telegram failure is retried on the next run; a missing notify-send is not, or it would retry every minute.
            // Either way the reminder still shows as due in the app.
            if ($telegram->isConfigured() && ! $telegram->send($this->message($reminder))) {
                continue;
            }
            $reminder->update(['notified_at' => now()]);
        }

        $this->info("Notified {$reminders->count()} reminder(s).");

        return self::SUCCESS;
    }

    private function message(Reminder $reminder): string
    {
        $lines = ['⏰ <b>'.e($reminder->title).'</b>', 'Due '.$reminder->due_at->format('D j M H:i')];
        if ($reminder->notes) {
            $lines[] = e($reminder->notes);
        }
        if ($reminder->recurrence !== 'none') {
            $lines[] = 'Repeats '.strtolower(Reminder::RECURRENCES[$reminder->recurrence]);
        }
        $lines[] = '<a href="'.e(route('reminders.index')).'">Open reminders</a>';

        return implode("\n", $lines);
    }
}
