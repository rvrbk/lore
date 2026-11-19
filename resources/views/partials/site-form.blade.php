<form method="POST" action="{{ route('analytics.store') }}" class="grid gap-3 sm:grid-cols-2">
    @csrf
    <input name="name" value="{{ old('name') }}" placeholder="Name, e.g. verbeek-ug" required maxlength="100" aria-label="Name" class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
    <input name="url" value="{{ old('url') }}" placeholder="https://example.com" required aria-label="URL" class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
    <input name="property_id" value="{{ old('property_id') }}" placeholder="GA4 property ID (optional), e.g. 547074352" inputmode="numeric" aria-label="GA4 property ID" class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm sm:col-span-2">
    @if($errors->any())<p class="text-sm text-red-400 sm:col-span-2">{{ $errors->first() }}</p>@endif
    <p class="text-xs text-zinc-500 sm:col-span-2">The URL is checked every minute on Server health. The property ID (GA4 Admin → Property details, a number, not G-XXXX) adds its analytics below.</p>
    <div class="sm:col-span-2"><button class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Add site</button></div>
</form>
