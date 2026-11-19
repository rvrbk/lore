<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

#[Signature('reminders:notify')]
#[Description('Send a desktop notification for every reminder that has come due')]
class NotifyDueReminders extends Command
{
    public function handle(): int
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
            // Mark as notified either way so a missing notify-send does not retry every minute.
            // It still shows as due in the app.
            $reminder->update(['notified_at' => now()]);
        }

        $this->info("Notified {$reminders->count()} reminder(s).");

        return self::SUCCESS;
    }
}
