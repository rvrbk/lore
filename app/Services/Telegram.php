<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends messages through a Telegram bot (Bot API). Disabled until a bot token and chat id are configured.
 */
class Telegram
{
    public function isConfigured(): bool
    {
        return (bool) config('services.telegram.token') && (bool) config('services.telegram.chat_id');
    }

    /**
     * Send an HTML-formatted message to the configured chat.
     */
    public function send(string $html): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::timeout(10)->post($this->endpoint('sendMessage'), [
                'chat_id' => config('services.telegram.chat_id'),
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
     * Chats that have recently messaged the bot, to find the chat id.
     *
     * @return array<int, array{id: int|string, name: string}>
     */
    public function recentChats(): array
    {
        if (! config('services.telegram.token')) {
            return [];
        }

        try {
            $updates = Http::timeout(10)->get($this->endpoint('getUpdates'))->json('result') ?? [];
        } catch (Throwable) {
            return [];
        }

        return collect($updates)
            ->map(fn (array $update) => $update['message']['chat'] ?? null)
            ->filter()
            ->unique('id')
            ->map(fn (array $chat) => ['id' => $chat['id'], 'name' => $chat['first_name'] ?? $chat['title'] ?? $chat['username'] ?? 'chat'])
            ->values()->all();
    }

    private function endpoint(string $method): string
    {
        return 'https://api.telegram.org/bot'.config('services.telegram.token').'/'.$method;
    }
}
