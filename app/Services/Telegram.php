<?php

namespace App\Services;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends messages through Lore's Telegram bot (Bot API). The bot is shared; each user connects their own chat to it
 * on their Telegram settings page. Disabled until a bot token is configured.
 */
class Telegram
{
    public function isConfigured(): bool
    {
        return (bool) config('services.telegram.token');
    }

    /** Whether messages for this user can be delivered: the bot exists and they connected a chat. */
    public function canMessage(?User $user): bool
    {
        return $this->isConfigured() && filled($user?->telegram_chat_id);
    }

    /**
     * Sends an HTML-formatted message to everyone who connected Telegram, for things that belong to nobody in particular
     * (the commit streak, problems not tied to a monitor).
     */
    public function send(string $html): bool
    {
        $recipients = User::whereNotNull('telegram_chat_id')->get();
        if (! $this->isConfigured() || $recipients->isEmpty()) {
            return false;
        }

        // Every recipient is tried, and it only counts as delivered when all of them got it.
        return $recipients->map(fn (User $user) => $this->sendTo($user, $html))->every(fn (bool $sent) => $sent);
    }

    /**
     * Sends a message about a monitor to the users subscribed to it. Without a monitor it goes to everyone, like send().
     */
    public function sendAbout(?Monitor $monitor, string $html): bool
    {
        if ($monitor === null) {
            return $this->send($html);
        }

        $recipients = $monitor->subscribers()->whereNotNull('telegram_chat_id')->get();
        if (! $this->isConfigured() || $recipients->isEmpty()) {
            return false;
        }

        return $recipients->map(fn (User $user) => $this->sendTo($user, $html))->every(fn (bool $sent) => $sent);
    }

    /**
     * Sends an HTML-formatted message to one user's chat, for things that belong to them.
     */
    public function sendTo(User $user, string $html): bool
    {
        if (! $this->canMessage($user)) {
            return false;
        }

        try {
            $response = Http::timeout(10)->post($this->endpoint('sendMessage'), [
                'chat_id' => $user->telegram_chat_id,
                'text' => $html,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);
        } catch (Throwable $e) {
            Log::warning('Telegram message failed', ['error' => $e->getMessage()]);

            return false;
        }

        if ($response->failed()) {
            Log::warning('Telegram message failed', ['status' => $response->status(), 'error' => $response->json('description')]);
        }

        return $response->successful();
    }

    /**
     * The bot's username, for the link that opens the chat with it.
     */
    public function botUsername(): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        return Cache::remember('telegram.bot-username', now()->addDay(), function (): ?string {
            try {
                return Http::timeout(10)->get($this->endpoint('getMe'))->json('result.username');
            } catch (Throwable) {
                return null;
            }
        });
    }

    /**
     * The chat that sent the bot "/start {code}" (or just the code), which proves the chat belongs to whoever holds the code.
     *
     * @return array{id: string, name: string}|null
     */
    public function chatForLinkCode(string $code): ?array
    {
        foreach (array_reverse($this->recentMessages()) as $message) {
            $text = trim((string) ($message['text'] ?? ''));
            if ($text === $code || $text === '/start '.$code) {
                $chat = $message['chat'];

                return ['id' => (string) $chat['id'], 'name' => $chat['first_name'] ?? $chat['title'] ?? $chat['username'] ?? 'chat'];
            }
        }

        return null;
    }

    /**
     * Chats that have recently messaged the bot.
     *
     * @return array<int, array{id: int|string, name: string}>
     */
    public function recentChats(): array
    {
        return collect($this->recentMessages())
            ->pluck('chat')
            ->unique('id')
            ->map(fn (array $chat) => ['id' => $chat['id'], 'name' => $chat['first_name'] ?? $chat['title'] ?? $chat['username'] ?? 'chat'])
            ->values()->all();
    }

    /**
     * The latest messages sent to the bot, oldest first. Telegram keeps them for 24 hours.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentMessages(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $updates = Http::timeout(10)->get($this->endpoint('getUpdates'), ['offset' => -100])->json('result') ?? [];
        } catch (Throwable) {
            return [];
        }

        return collect($updates)->map(fn (array $update) => $update['message'] ?? null)->filter(fn ($message) => isset($message['chat']['id']))->values()->all();
    }

    private function endpoint(string $method): string
    {
        return 'https://api.telegram.org/bot'.config('services.telegram.token').'/'.$method;
    }
}
