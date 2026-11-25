<x-layouts.app title="Two-factor authentication">
    <h1 class="text-2xl font-semibold">Settings</h1>
    <div class="mt-4">@include('settings._nav')</div>

    @if(session('status'))<p class="mb-4 text-sm text-emerald-400">{{ session('status') }}</p>@endif

    <section class="max-w-2xl space-y-4 border border-zinc-800 bg-zinc-900 p-4 text-sm">
        <p class="text-zinc-400">A second step when you sign in: a six digit code from an authenticator app. The password vault only opens for accounts that have it on.</p>

        @if($recoveryCodes)
            <div>
                <p class="font-medium text-emerald-400">Two-factor authentication is on.</p>
                <p class="mt-2 text-zinc-400">Save these recovery codes somewhere safe, outside this server. Each one works once if you lose your phone. They are not shown again.</p>
                <ul class="mt-2 grid grid-cols-2 gap-1 font-mono">@foreach($recoveryCodes as $code)<li>{{ $code }}</li>@endforeach</ul>
            </div>
        @elseif($enabled)
            <p><span class="text-emerald-400">●</span> Two-factor authentication is on. {{ $recoveryLeft }} recovery codes left.</p>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('settings.two-factor.recovery-codes') }}">@csrf<button class="rounded border px-3 py-1.5 hover:bg-zinc-800">Make new recovery codes</button></form>
                <form method="POST" action="{{ route('settings.two-factor.disable') }}" onsubmit="return confirm('Turn off two-factor authentication? The vault will lock.')">@csrf @method('DELETE')<button class="rounded border px-3 py-1.5 text-red-400 hover:bg-red-500/10">Turn off</button></form>
            </div>
        @elseif($secret)
            <p>Add this key to an authenticator app (choose "enter a setup key", time based), then type the code it shows.</p>
            <p class="select-all break-all font-mono text-base">{{ $secret }}</p>
            <p class="break-all text-xs text-zinc-500">Or open <span class="select-all">{{ $uri }}</span> on the phone that has the app.</p>
            <form method="POST" action="{{ route('settings.two-factor.confirm') }}" class="flex items-end gap-2">
                @csrf
                <label class="block"><span class="text-zinc-400">Code</span><input name="code" required autocomplete="one-time-code" inputmode="numeric" maxlength="7" class="mt-1 block w-32 rounded-md border border-zinc-700 px-3 py-2"></label>
                <button class="rounded border px-3 py-2 hover:bg-zinc-800">Turn on</button>
            </form>
            @error('code')<p class="text-red-400">{{ $message }}</p>@enderror
        @else
            <form method="POST" action="{{ route('settings.two-factor.start') }}">@csrf<button class="rounded border px-3 py-1.5 hover:bg-zinc-800">Set up two-factor authentication</button></form>
        @endif
    </section>
</x-layouts.app>
