<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Telegram;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('telegram:test')]
#[Description('Send a test message through the Telegram bot, or list the chats that have messaged it')]
class TelegramTest extends Command
{
    public function handle(Telegram $telegram): int
    {
        if (! $telegram->isConfigured()) {
            $this->error('Set TELEGRAM_BOT_TOKEN in .env first.');

            return self::FAILURE;
        }

        $recipients = User::whereNotNull('telegram_chat_id')->get();
        if ($recipients->isEmpty()) {
            $this->warn('Nobody has connected Telegram yet. Users do that on their Telegram settings page.');
            $chats = $telegram->recentChats();
            if ($chats !== []) {
                $this->info('Chats that have messaged the bot:');
                $this->table(['Chat id', 'Name'], $chats);
            }

            return self::FAILURE;
        }

        $failed = false;
        foreach ($recipients as $user) {
            if ($telegram->sendTo($user, '✅ <b>Lore</b> can reach you on Telegram.')) {
                $this->info("Test message sent to {$user->username}.");
            } else {
                $this->error("Telegram rejected the message for {$user->username}. Check the token and chat id (see storage/logs).");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
