<x-layouts.app title="MCP access">
    <h1 class="text-2xl font-semibold">Settings</h1>
    <div class="mt-4">@include('settings._nav')</div>

    @if(session('status'))<p class="mb-4 text-sm text-emerald-400">{{ session('status') }}</p>@endif

    <section class="max-w-2xl space-y-4 border border-zinc-800 bg-zinc-900 p-4 text-sm">
        <p class="text-zinc-400">Let an AI client such as Claude Code read and change your wiki pages, Kanban board and reminders through Lore's MCP server. Each client gets its own token, which acts as you and can be revoked here at any time.</p>

        <div>
            <div class="text-zinc-400">Endpoint</div>
            <code class="mt-1 block select-all border border-zinc-700 px-2 py-1">{{ $endpoint }}</code>
        </div>

        @if(session('new_token'))
            <div class="border border-emerald-700 bg-emerald-500/10 p-3">
                <p class="font-medium text-emerald-400">Your new token. Copy it now: it is not shown again.</p>
                <code class="mt-2 block select-all break-all border border-zinc-700 px-2 py-1">{{ session('new_token') }}</code>
                <p class="mt-3 text-zinc-400">Add it to Claude Code with:</p>
                <code class="mt-1 block select-all break-all border border-zinc-700 px-2 py-1">claude mcp add --transport http lore {{ $endpoint }} --header "Authorization: Bearer {{ session('new_token') }}"</code>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.mcp.store') }}" class="flex items-end gap-2">
            @csrf
            <label class="block flex-1"><span class="text-zinc-400">Name for a new token</span>
                <input name="name" value="{{ old('name') }}" required maxlength="60" placeholder="Claude Code on my laptop" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
                @error('name')<span class="mt-1 block text-red-400">{{ $message }}</span>@enderror
            </label>
            <button class="rounded bg-indigo-600 px-3 py-2 text-white hover:bg-indigo-700">Create token</button>
        </form>

        <div>
            <h2 class="font-semibold">Your tokens</h2>
            @forelse($tokens as $token)
                <div class="mt-2 flex items-center justify-between gap-3 border-t border-zinc-800 pt-2">
                    <div>
                        <div>{{ $token->name }}</div>
                        <div class="text-xs text-zinc-500">Created {{ $token->created_at->diffForHumans() }} · {{ $token->last_used_at ? 'last used '.$token->last_used_at->diffForHumans() : 'never used' }}</div>
                    </div>
                    <form method="POST" action="{{ route('settings.mcp.destroy', $token->id) }}" onsubmit="return confirm('Revoke this token? Clients using it stop working.')">@csrf @method('DELETE')<button class="rounded border px-3 py-1.5 text-red-400 hover:bg-red-500/10">Revoke</button></form>
                </div>
            @empty
                <p class="mt-2 text-zinc-400">No tokens yet.</p>
            @endforelse
        </div>
    </section>
</x-layouts.app>
