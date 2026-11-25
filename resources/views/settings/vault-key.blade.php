<x-layouts.app title="Vault key">
    <h1 class="text-2xl font-semibold">Settings</h1>
    <div class="mt-4">@include('settings._nav')</div>

    @if(session('status'))<p class="mb-4 text-sm text-emerald-400">{{ session('status') }}</p>@endif

    <section class="max-w-2xl space-y-4 border border-zinc-800 bg-zinc-900 p-4 text-sm">
        <p class="text-zinc-400">Encrypted vault exports are locked with <strong>your own key pair</strong>. This page holds the <strong>public</strong> half, which can only lock files. The <strong>private</strong> half stays on your own computer and is the only thing that opens them. Lore never sees it and cannot open your exports.</p>

        @if($hasKey)
            <p><span class="text-emerald-400">●</span> A public key is set. Fingerprint: <code class="select-all">{{ $fingerprint }}</code></p>
            <p class="text-xs text-zinc-500">The key tool shows the same fingerprint when it makes a key. Exports made before you change the key can only be opened with the old private key, so keep it.</p>
        @else
            <p class="text-zinc-400">No key yet, so encrypted export is off.</p>
        @endif

        <div class="space-y-1">
            <h2 class="font-semibold">Make a key (once)</h2>
            <ol class="list-decimal space-y-1 pl-5 text-zinc-300">
                <li><a href="{{ route('vault.export.decrypt-tool') }}" class="underline">Download the key tool</a> and open it in your browser. It works offline.</li>
                <li>Press "Make a new key". Save the <strong>private key file</strong> somewhere safe and offline (a password manager or a USB stick). If you lose it, your encrypted exports can never be opened.</li>
                <li>Copy the <strong>public key</strong> it shows (it starts with <code>lorepub1:</code>) and paste it below.</li>
            </ol>
        </div>

        <form method="POST" action="{{ route('settings.vault-key.update') }}" class="space-y-2">
            @csrf @method('PUT')
            <label class="block"><span class="text-zinc-400">Your public key</span>
                <input name="vault_public_key" value="{{ old('vault_public_key') }}" required autocomplete="off" spellcheck="false" placeholder="lorepub1:…" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2 font-mono">
                @error('vault_public_key')<span class="mt-1 block text-red-400">{{ $message }}</span>@enderror
            </label>
            <button class="rounded border px-3 py-1.5 hover:bg-zinc-800">{{ $hasKey ? 'Replace the key' : 'Save the key' }}</button>
        </form>

        @if($hasKey)
            <form method="POST" action="{{ route('settings.vault-key.destroy') }}" onsubmit="return confirm('Remove your public key? Encrypted export will be off.')">@csrf @method('DELETE')<button class="rounded border px-3 py-1.5 text-red-400 hover:bg-red-500/10">Remove the key</button></form>
        @endif
    </section>
</x-layouts.app>
