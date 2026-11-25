@php $editing = isset($item); @endphp
<form method="POST" action="{{ $editing ? route('vault.update', $item) : route('vault.store') }}" autocomplete="off" data-vault-form class="max-w-xl space-y-3 text-sm">
    @csrf
    @if($editing) @method('PUT') @endif
    <label class="block"><span class="text-zinc-400">Name</span>
        <input name="name" value="{{ old('name', $item->name ?? '') }}" required maxlength="255" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
        @error('name')<span class="mt-1 block text-red-400">{{ $message }}</span>@enderror
    </label>
    <label class="block"><span class="text-zinc-400">URL</span>
        <input name="url" value="{{ old('url', $item->url ?? '') }}" maxlength="255" placeholder="https://" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
        @error('url')<span class="mt-1 block text-red-400">{{ $message }}</span>@enderror
    </label>
    <label class="block"><span class="text-zinc-400">Username</span>
        <input name="vault_username" value="{{ $editing ? $item->username : '' }}" maxlength="1000" autocomplete="off" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
        @error('vault_username')<span class="mt-1 block text-red-400">{{ $message }}</span>@enderror
    </label>
    <div>
        <label class="block"><span class="text-zinc-400">Password{{ $editing ? ' (leave empty to keep the current one)' : '' }}</span>
            <input type="password" name="password" data-vault-password @required(! $editing) maxlength="4096" autocomplete="new-password" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2 font-mono">
        </label>
        @error('password')<span class="mt-1 block text-red-400">{{ $message }}</span>@enderror
        <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-zinc-400">
            <label class="flex items-center gap-1">Length <input type="number" data-gen-length value="20" min="8" max="128" class="w-16 rounded border border-zinc-700 px-1.5 py-1"></label>
            <label class="flex items-center gap-1"><input type="checkbox" data-gen-lower checked class="accent-indigo-500">a-z</label>
            <label class="flex items-center gap-1"><input type="checkbox" data-gen-upper checked class="accent-indigo-500">A-Z</label>
            <label class="flex items-center gap-1"><input type="checkbox" data-gen-digits checked class="accent-indigo-500">0-9</label>
            <label class="flex items-center gap-1"><input type="checkbox" data-gen-symbols checked class="accent-indigo-500">!@#</label>
            <button type="button" data-gen-run class="rounded border px-2 py-1 text-zinc-200 hover:bg-zinc-800">Generate</button>
            <button type="button" data-gen-show class="rounded border px-2 py-1 text-zinc-200 hover:bg-zinc-800">Show</button>
        </div>
    </div>
    <label class="block"><span class="text-zinc-400">Notes</span>
        <textarea name="vault_notes" rows="3" maxlength="10000" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">{{ $editing ? $item->notes : '' }}</textarea>
        @error('vault_notes')<span class="mt-1 block text-red-400">{{ $message }}</span>@enderror
    </label>
    <button class="rounded border px-3 py-1.5 hover:bg-zinc-800">Save</button>
    @if($editing)<a href="{{ route('vault.index') }}" class="ml-2 text-zinc-400 underline">Cancel</a>@endif
</form>
