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
        <section class="mt-6 max-w-2xl space-y-5 border border-zinc-800 bg-zinc-900 p-4 text-sm">
            <p class="text-zinc-400">Everyone connects their own Telegram account, so you will only get your own messages. Lore never sees your phone number or contacts, only the chat you open with its bot.</p>

            <div>
                <div class="font-medium">1. Get Telegram</div>
                <p class="mt-1 text-zinc-400">Skip this if you already use it. Otherwise install Telegram on your phone or computer (<a href="https://telegram.org/apps" target="_blank" rel="noopener" class="underline">telegram.org/apps</a>) and sign up with your phone number.</p>
            </div>

            <div>
                <div class="font-medium">2. Open the Lore bot and press Start</div>
                @if($startUrl)
                    <p class="mt-1 text-zinc-400">On the device where Telegram is installed, open this link. Telegram opens the chat with the bot; press the <b>Start</b> button at the bottom.</p>
                    <a href="{{ $startUrl }}" target="_blank" rel="noopener" class="mt-2 inline-block rounded bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">Open @{{ $botUsername }} in Telegram</a>
                    <p class="mt-3 text-zinc-400">The link does not work, or you are on a different device than Telegram? Open Telegram, tap the search field, search for <b>&#64;{{ $botUsername }}</b>, open the chat and send it exactly this message:</p>
                @else
                    <p class="mt-1 text-zinc-400">Open Telegram, tap the search field, search for Lore's bot, open the chat and send it exactly this message:</p>
                @endif
                <code class="mt-2 inline-block select-all border border-zinc-700 px-2 py-1">/start {{ $code }}</code>
                <p class="mt-2 text-xs text-zinc-500">The bot does not answer at this point. That is normal; the next step makes the connection.</p>
            </div>

            <div>
                <div class="font-medium">3. Come back here and check the connection</div>
                <p class="mt-1 text-zinc-400">Wait a few seconds after sending, then press the button. Lore finds your message, connects your chat and sends you a confirmation in Telegram.</p>
                <form method="POST" action="{{ route('settings.telegram.check') }}" class="mt-2">@csrf<button class="rounded border px-3 py-1.5 hover:bg-zinc-800">Check connection</button></form>
            </div>

            <details class="text-zinc-400">
                <summary class="cursor-pointer text-zinc-300">It does not connect</summary>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>The message must contain your code exactly as shown above, and it must have been sent in the last 24 hours.</li>
                    <li>Telegram can take a few seconds. Wait a moment and press <b>Check connection</b> again.</li>
                    <li>Did you reload this page after sending? The code stays the same until you connect, so that is fine.</li>
                    <li>A Telegram chat can only be linked to one Lore account. If you want to move it, disconnect it on the other account first.</li>
                </ul>
            </details>
            <p class="text-xs text-zinc-500">The code is private to you: whoever sends it to the bot gets linked to your account. You can disconnect at any time on this page, or block the bot in Telegram.</p>
        </section>
    @endif
</x-layouts.app>
