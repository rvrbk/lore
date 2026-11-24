<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Telegram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Where a user connects their own Telegram chat to Lore's bot to receive their reminders, bookings and the shared alerts.
 */
class TelegramSettingsController extends Controller
{
    public function show(Request $request, Telegram $telegram)
    {
        $user = $request->user();
        if ($telegram->isConfigured() && ! $user->telegram_chat_id && ! $user->telegram_link_code) {
            $user->forceFill(['telegram_link_code' => Str::random(24)])->save();
        }

        $username = $telegram->botUsername();

        return view('settings.telegram', [
            'botConfigured' => $telegram->isConfigured(),
            'botUsername' => $username,
            'connected' => filled($user->telegram_chat_id),
            'digestSubscribed' => $user->digest_subscribed,
            'code' => $user->telegram_link_code,
            'startUrl' => $username && $user->telegram_link_code ? 'https://t.me/'.$username.'?start='.$user->telegram_link_code : null,
        ]);
    }

    public function check(Request $request, Telegram $telegram): RedirectResponse
    {
        $user = $request->user();
        $chat = $user->telegram_link_code ? $telegram->chatForLinkCode($user->telegram_link_code) : null;

        if ($chat === null) {
            return back()->withErrors(['telegram' => 'The bot has not received your code yet. Send it the message from step 1, wait a few seconds, then check again.']);
        }

        if (User::where('telegram_chat_id', $chat['id'])->whereKeyNot($user->id)->exists()) {
            return back()->withErrors(['telegram' => 'That Telegram chat is already connected to another account.']);
        }

        $user->forceFill(['telegram_chat_id' => $chat['id'], 'telegram_link_code' => null])->save();
        $telegram->sendTo($user, '✅ <b>Lore</b> is connected. Your reminders and bookings will arrive here.');

        return redirect()->route('settings.telegram')->with('status', "Connected to {$chat['name']}'s chat.");
    }

    public function test(Request $request, Telegram $telegram): RedirectResponse
    {
        return $telegram->sendTo($request->user(), '✅ <b>Lore</b> can reach you on Telegram.')
            ? back()->with('status', 'Test message sent.')
            : back()->withErrors(['telegram' => 'Telegram did not accept the message. Disconnect and connect again.']);
    }

    public function digest(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['digest_subscribed' => $request->boolean('digest_subscribed')])->save();

        return redirect()->route('settings.telegram')->with('status', $request->boolean('digest_subscribed') ? 'You will get the morning digest.' : 'No more morning digest.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['telegram_chat_id' => null, 'telegram_link_code' => null])->save();

        return redirect()->route('settings.telegram')->with('status', 'Telegram disconnected.');
    }
}
