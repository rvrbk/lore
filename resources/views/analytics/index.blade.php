<x-layouts.app title="Analytics">
    <h1 class="text-2xl font-semibold">Analytics</h1>
    <p class="mt-2 text-sm text-zinc-400">Add a site once to monitor its uptime, show its Google Analytics on Home and collect its click heatmaps. The service account must be a Viewer on each property.</p>

    @unless($hasCredentials)
        <p class="mt-4 border border-red-500/50 bg-zinc-900 p-3 text-sm text-red-400">No service account key found. Set <code>GA_CREDENTIALS</code> in the server's .env to the path of the JSON key.</p>
    @endunless

    <details class="mt-5 border border-zinc-800 bg-zinc-900 p-3" @if($errors->any()) open @endif>
        <summary class="cursor-pointer text-sm font-medium">Add a site</summary>
        <div class="mt-3">@include('partials.site-form')</div>
    </details>

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

    @if($analytics)
        <div class="mt-6">@include('partials.world-map')</div>
    @endif

    @if($searchSites->isNotEmpty())
        <section class="mt-8">
            <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-zinc-400">Google Search <span class="ml-2 normal-case">last {{ \App\Services\SearchConsole::DAYS }} days</span></h2>
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($searchSites->whereNotNull('report') as $row)
                    @php($report = $row['report'])
                    <li class="border border-zinc-800 bg-zinc-900 p-3">
                        <a href="{{ route('monitors.show', $row['monitor']) }}" class="block truncate text-sm font-medium text-zinc-100 hover:underline">{{ $row['monitor']->name }}</a>
                        <div class="mt-2">@include('partials.trend', ['values' => $report['daily_impressions'], 'label' => 'Search impressions per day for '.$row['monitor']->name])</div>
                        <dl class="mt-3 grid grid-cols-3 gap-2 text-xs text-zinc-400">
                            <div><dd class="text-xl font-semibold text-indigo-400">{{ number_format($report['clicks']) }}</dd><dt>Clicks</dt></div>
                            <div><dd class="text-xl font-semibold text-zinc-100">{{ number_format($report['impressions']) }}</dd><dt>Shown</dt></div>
                            <div><dd class="text-xl font-semibold text-zinc-100">{{ $report['position'] ?? '—' }}</dd><dt>Position</dt></div>
                        </dl>
                        @if(($report['home']['verdict'] ?? 'PASS') !== 'PASS' || $report['sitemap_problems'])<p class="mt-2 text-xs text-red-400">@if(($report['home']['verdict'] ?? 'PASS') !== 'PASS')Home page: {{ $report['home']['state'] ?: $report['home']['verdict'] }} @endif @if($report['sitemap_problems'])· {{ $report['sitemap_problems'] }} sitemap {{ \Illuminate\Support\Str::plural('error', $report['sitemap_problems']) }}@endif</p>@endif
                    </li>
                @endforeach
            </ul>
            @if($searchSites->whereNull('report')->isNotEmpty())
                <p class="mt-3 text-xs text-zinc-500">Not found in Search Console, or no answer yet: {{ $searchSites->whereNull('report')->map(fn ($row) => $row['monitor']->name)->implode(', ') }}. Add the service account as a user on each property.</p>
            @endif
        </section>
    @endif

    @include('partials.heatmap-overview')
</x-layouts.app>
