<x-layouts.app title="Telegram">
    <h1 class="text-2xl font-semibold">Settings</h1>
    <div class="mt-4">@include('settings._nav')</div>
    <p class="mt-2 max-w-2xl text-sm text-zinc-400">Get your reminders and Pointy bookings as Telegram messages. Down and up alerts, incidents and failed deploy checks go to the people subscribed to that site (on the Server health page). The commit streak nudge is not tied to anyone, so everyone who connected Telegram gets it. The morning digest is optional.</p>

    @if(session('status'))<p class="mt-4 text-sm text-emerald-400">{{ session('status') }}</p>@endif
    @error('telegram')<p class="mt-4 text-sm text-red-400">{{ $message }}</p>@enderror

    @if(! $botConfigured)
        <p class="mt-6 text-sm text-zinc-400">Telegram is not set up on this Lore yet. The person who runs it has to create a bot and add its token as <code>TELEGRAM_BOT_TOKEN</code>.</p>
    @elseif($connected)
        <section class="mt-6 border border-zinc-800 bg-zinc-900 p-4">
            <p class="text-sm"><span class="text-emerald-400">●</span> Connected. Your reminders and bookings go to your Telegram chat with the bot.</p>
            <form method="POST" action="{{ route('settings.telegram.digest') }}" class="mt-4 text-sm">
                @csrf @method('PUT')
                <input type="hidden" name="digest_subscribed" value="0">
                <label class="flex items-start gap-2"><input type="checkbox" name="digest_subscribed" value="1" @checked($digestSubscribed) onchange="this.form.submit()" class="mt-1 accent-indigo-500"><span>Send me the morning digest at 07:30<span class="block text-xs text-zinc-500">Your reminders due today, open incidents, the Kanban, yesterday's visitors and upcoming expiries.</span></span></label>
            </form>
            <div class="mt-4 flex gap-2 text-sm">
                <form method="POST" action="{{ route('settings.telegram.test') }}">@csrf<button class="rounded border px-3 py-1.5 hover:bg-zinc-800">Send a test message</button></form>
                <form method="POST" action="{{ route('settings.telegram.disconnect') }}" onsubmit="return confirm('Stop sending Telegram messages?')">@csrf @method('DELETE')<button class="rounded border px-3 py-1.5 text-red-400 hover:bg-red-500/10">Disconnect</button></form>
            </div>
        </section>
    @else
        <section class="mt-6 max-w-2xl space-y-4 border border-zinc-800 bg-zinc-900 p-4 text-sm">
            <div>
                <div class="font-medium">1. Send the bot your code</div>
                @if($startUrl)
                    <p class="mt-1 text-zinc-400">Open the bot in Telegram and press <b>Start</b>:</p>
                    <a href="{{ $startUrl }}" target="_blank" rel="noopener" class="mt-2 inline-block rounded bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">Open @{{ $botUsername }}</a>
                    <p class="mt-2 text-zinc-400">Or search for <b>&#64;{{ $botUsername }}</b> in Telegram yourself and send it this message:</p>
                @else
                    <p class="mt-1 text-zinc-400">Find Lore's bot in Telegram and send it this message:</p>
                @endif
                <code class="mt-2 inline-block select-all border border-zinc-700 px-2 py-1">/start {{ $code }}</code>
            </div>
            <div>
                <div class="font-medium">2. Check the connection</div>
                <p class="mt-1 text-zinc-400">Wait a few seconds after sending the message, then:</p>
                <form method="POST" action="{{ route('settings.telegram.check') }}" class="mt-2">@csrf<button class="rounded border px-3 py-1.5 hover:bg-zinc-800">Check connection</button></form>
            </div>
            <p class="text-xs text-zinc-500">The code is private to you: whoever sends it to the bot gets linked to your account.</p>
        </section>
    @endif
</x-layouts.app>
