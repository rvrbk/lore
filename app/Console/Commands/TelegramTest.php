<?php

namespace App\Console\Commands;

use App\Services\Telegram;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('telegram:test')]
#[Description('Send a test message through the Telegram bot, or list chat ids that have messaged it')]
class TelegramTest extends Command
{
    public function handle(Telegram $telegram): int
    {
        if (! config('services.telegram.token')) {
            $this->error('Set TELEGRAM_BOT_TOKEN in .env first.');

            return self::FAILURE;
        }

        if (! config('services.telegram.chat_id')) {
            $chats = $telegram->recentChats();
            if ($chats === []) {
                $this->warn('No chat id yet. Send any message to your bot in Telegram, then run this again.');
            } else {
                $this->info('Set TELEGRAM_CHAT_ID to one of these:');
                $this->table(['Chat id', 'Name'], $chats);
            }

            return self::FAILURE;
        }

        if (! $telegram->send('✅ <b>Lore</b> can reach you on Telegram.')) {
            $this->error('Telegram rejected the message. Check the token and chat id (see storage/logs).');

            return self::FAILURE;
        }

        $this->info('Test message sent.');

        return self::SUCCESS;
    }
}
