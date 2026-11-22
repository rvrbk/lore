@php
    $site = $site ?? null;
    $field = 'rounded-md border border-zinc-700 px-3 py-1.5 text-sm';
@endphp
<form method="POST" action="{{ $site ? route('pointy.sites.update', $site) : route('pointy.sites.store') }}" class="grid gap-3 sm:grid-cols-2">
    @csrf
    @if($site) @method('PUT') @endif
    <input name="name" value="{{ old('name', $site?->name) }}" placeholder="Site name, e.g. verbeek.ug" required maxlength="100" aria-label="Site name" class="{{ $field }}">
    <input name="key" value="{{ old('key', $site?->key) }}" placeholder="Site key for ?site=, e.g. verbeek-ug" required maxlength="60" aria-label="Site key" class="{{ $field }}">
    <textarea name="css" rows="8" maxlength="20000" spellcheck="false" aria-label="Custom CSS" placeholder=".pointy-submit { background: #0a7; border-radius: 999px; }" class="{{ $field }} w-full font-mono text-xs sm:col-span-2">{{ old('css', $site?->css) }}</textarea>
    @if($errors->any())<p class="text-sm text-red-400 sm:col-span-2">{{ $errors->first() }}</p>@endif
    <div class="sm:col-span-2"><button class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">{{ $site ? 'Save' : 'Add site' }}</button></div>
</form>
