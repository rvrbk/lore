<x-layouts.app title="Analytics">
    <h1 class="text-2xl font-semibold">Analytics</h1>
    <p class="mt-2 text-sm text-zinc-400">Add a site once to monitor its uptime and show its Google Analytics on Home. The service account must be a Viewer on each property.</p>

    @unless($hasCredentials)
        <p class="mt-4 border border-red-500/50 bg-zinc-900 p-3 text-sm text-red-400">No service account key found. Set <code>GA_CREDENTIALS</code> in the server's .env to the path of the JSON key.</p>
    @endunless

    <form method="POST" action="{{ route('analytics.store') }}" class="mt-5 grid gap-3 border border-zinc-800 bg-zinc-900 p-3 sm:grid-cols-2">
        @csrf
        <h2 class="sm:col-span-2">Add a site</h2>
        <input name="name" value="{{ old('name') }}" placeholder="Name, e.g. verbeek-ug" required maxlength="100" aria-label="Name" class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
        <input name="url" value="{{ old('url') }}" placeholder="https://example.com" required aria-label="URL" class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
        <input name="property_id" value="{{ old('property_id') }}" placeholder="GA4 property ID (optional), e.g. 547074352" inputmode="numeric" aria-label="GA4 property ID" class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm sm:col-span-2">
        @if($errors->any())<p class="text-sm text-red-400 sm:col-span-2">{{ $errors->first() }}</p>@endif
        <p class="text-xs text-zinc-500 sm:col-span-2">The URL is checked every minute on Server health. The property ID (GA4 Admin → Property details, a number, not G-XXXX) adds its analytics below.</p>
        <div class="sm:col-span-2"><button class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Add site</button></div>
    </form>

    @if($sites->isNotEmpty())
        <ul class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($sites as $site)
                @php($stats = collect($analytics ?? [])->firstWhere('name', $site->name))
                <li class="border border-zinc-800 bg-zinc-900 p-3">
                    @if($stats)
                        @include('partials.analytics-tile', ['site' => $stats])
                    @else
                        <div class="truncate text-sm font-medium text-zinc-100">{{ $site->name }}</div>
                        <p class="mt-2 text-xs text-zinc-400">{{ $configured ? 'No data yet.' : 'Waiting for the service account key.' }}</p>
                    @endif
                    <div class="mt-3 flex items-center justify-between border-t border-zinc-800 pt-2 text-xs text-zinc-500">
                        <span>Property {{ $site->property_id }}</span>
                        <form method="POST" action="{{ route('analytics.destroy', $site) }}" onsubmit="return confirm('Remove {{ e($site->name) }}?')">@csrf @method('DELETE')<button class="text-red-400 hover:underline">Remove</button></form>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <p class="mt-6 text-sm text-zinc-400">No properties yet. Add the first one above.</p>
    @endif
</x-layouts.app>
