<x-layouts.app title="Vault">
    <h1 class="text-2xl font-semibold">Vault</h1>
    <p class="mt-1 max-w-2xl text-sm text-zinc-400">Passwords are encrypted on the server and only leave it when you press Reveal or Copy. The vault asks for your password again after {{ $idleLockMinutes }} idle minutes.</p>

    @if(session('status'))<p class="mt-3 text-sm text-emerald-400">{{ session('status') }}</p>@endif
    @if(session('error'))<p class="mt-3 text-sm text-red-400">{{ session('error') }}</p>@endif

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('vault.index') }}" class="flex gap-2 text-sm">
            <input name="q" value="{{ $search }}" placeholder="Search name or URL" class="w-64 rounded-md border border-zinc-700 px-3 py-1.5">
            <button class="rounded border px-3 py-1.5 hover:bg-zinc-800">Search</button>
        </form>
        <span class="ml-auto flex gap-3 text-sm text-zinc-400">
            <a href="{{ route('vault.export') }}" class="underline hover:text-zinc-100" title="Bitwarden import format. The file is not encrypted.">Export CSV</a>
            @if($canExportEncrypted)
                <a href="{{ route('vault.export', ['format' => 'encrypted']) }}" class="underline hover:text-zinc-100" title="Sealed to your public key. Only your private key opens it.">Export encrypted</a>
            @else
                <span class="cursor-help text-zinc-600" title="Set VAULT_PUBLIC_KEY in the server .env first (see docs/vault/README.md).">Export encrypted (not set up)</span>
            @endif
        </span>
    </div>

    <details class="mt-4 rounded-lg border border-zinc-800 bg-zinc-900 p-3" @if($errors->any()) open @endif>
        <summary class="cursor-pointer text-sm font-medium">New item</summary>
        <div class="mt-3">@include('vault._form')</div>
    </details>

    <ul class="mt-6 divide-y divide-zinc-800 border border-zinc-800 bg-zinc-900" data-vault-list data-lock-url="{{ route('password.confirm') }}">
        @forelse($items as $item)
            <li class="flex flex-wrap items-center gap-x-4 gap-y-1 px-3 py-2 text-sm">
                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium">{{ $item->name }}</div>
                    <div class="truncate text-xs text-zinc-500">
                        @if($item->url && preg_match('#^https?://#i', $item->url))<a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $item->url }}</a>@else{{ $item->url }}@endif
                        @if($item->last_revealed_at) · revealed {{ $item->last_revealed_at->diffForHumans() }}@endif
                    </div>
                </div>
                @if($item->username)<button type="button" data-copy-text="{{ $item->username }}" class="rounded border px-2 py-1 text-xs hover:bg-zinc-800" title="Copy username">{{ \Illuminate\Support\Str::limit($item->username, 24) }}</button>@endif
                <span data-vault-shown class="hidden max-w-xs select-all break-all font-mono text-xs"></span>
                <button type="button" data-vault-reveal="{{ route('vault.reveal', $item) }}" class="rounded border px-2 py-1 text-xs hover:bg-zinc-800">Reveal</button>
                <button type="button" data-vault-copy="{{ route('vault.reveal', $item) }}" class="rounded border px-2 py-1 text-xs hover:bg-zinc-800">Copy password</button>
                <a href="{{ route('vault.edit', $item) }}" class="text-xs text-zinc-400 underline hover:text-zinc-100">Edit</a>
                <form method="POST" action="{{ route('vault.destroy', $item) }}" onsubmit="return confirm('Delete {{ addslashes($item->name) }}?')">@csrf @method('DELETE')<button class="text-xs text-red-400 hover:underline">Delete</button></form>
            </li>
        @empty
            <li class="px-3 py-4 text-sm text-zinc-400">{{ $search !== '' ? 'Nothing matches.' : 'No items yet.' }}</li>
        @endforelse
    </ul>
</x-layouts.app>
