<?php

namespace App\Console\Commands;

use App\Services\CommitStreak;
use App\Services\Telegram;
use Illuminate\Console\Command;

class NudgeCommitStreak extends Command
{
    protected $signature = 'lore:streak-nudge';

    protected $description = 'Send a Telegram message when a commit streak is about to break';

    public function handle(CommitStreak $streak, Telegram $telegram): int
    {
        $state = $streak->current();
        if ($state['days'] > 0 && ! $state['committed_today']) {
            $telegram->send("🔥 Your commit streak is {$state['days']} days. One commit before midnight keeps it alive.");
            $this->info('Nudge sent.');
        }

        return self::SUCCESS;
    }
}
