<x-layouts.app title="Analytics">
    <h1 class="text-2xl font-semibold">Analytics</h1>
    <p class="mt-2 text-sm text-zinc-400">Add a site once to monitor its uptime and show its Google Analytics on Home. The service account must be a Viewer on each property.</p>

    @unless($hasCredentials)
        <p class="mt-4 border border-red-500/50 bg-zinc-900 p-3 text-sm text-red-400">No service account key found. Set <code>GA_CREDENTIALS</code> in the server's .env to the path of the JSON key.</p>
    @endunless

    <details class="mt-5 border border-zinc-800 bg-zinc-900 p-3" @if($errors->any()) open @endif>
        <summary class="cursor-pointer text-sm font-medium">Add a site</summary>
        <div class="mt-3">@include('partials.site-form')</div>
    </details>

    @if($analytics)
        <div class="mt-6">@include('partials.world-map')</div>
    @endif

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
