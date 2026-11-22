<x-layouts.app title="Heatmaps">
    <h1 class="text-2xl font-semibold">Heatmaps</h1>
    <p class="mt-2 text-sm text-zinc-400">Where visitors click on each site. Every site sends its clicks to Lore through a small script in its layout; sites added under Analytics get it automatically.</p>

    @if($monitors->isNotEmpty())
        <ul class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($monitors as $monitor)
                <li class="border border-zinc-800 bg-zinc-900 p-3">
                    <a href="{{ route('heatmaps.show', $monitor) }}" class="truncate font-medium text-zinc-100 hover:underline">{{ $monitor->name }}</a>
                    <div class="truncate text-xs text-zinc-400">{{ $monitor->host() }}</div>
                    <div class="mt-3 text-xs text-zinc-400"><span class="text-base text-zinc-100">{{ number_format($counts->get($monitor->id, 0)) }}</span> clicks · 30 days</div>
                    @unless($counts->get($monitor->id))
                        <details class="mt-2 text-xs text-zinc-400">
                            <summary class="cursor-pointer">Add the tracker by hand</summary>
                            <p class="mt-1">Put this before <code>&lt;/head&gt;</code> in the site's layout:</p>
                            <code class="mt-1 block break-all bg-zinc-950 p-2 text-zinc-300">{{ $snippet->tag($monitor) }}</code>
                        </details>
                    @endunless
                </li>
            @endforeach
        </ul>
    @else
        <p class="mt-6 text-sm text-zinc-400">No sites yet. Add one under <a href="{{ route('analytics.index') }}" class="text-indigo-300 hover:underline">Analytics</a>.</p>
    @endif
</x-layouts.app>
