<x-layouts.app title="Heatmaps">
    <h1 class="text-2xl font-semibold">Heatmaps</h1>
    <p class="mt-2 text-sm text-zinc-400">Where visitors click on each site. Every site sends its clicks to Lore through a small script in its layout; sites added under Analytics get it automatically.</p>

    @if($monitors->isNotEmpty())
        <ul class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($monitors as $monitor)
                <li class="border border-zinc-800 bg-zinc-900 p-3">
                    <a href="{{ route('heatmaps.show', $monitor) }}" class="truncate font-medium text-zinc-100 hover:underline">{{ $monitor->name }}</a>
                    <div class="truncate text-xs text-zinc-400">{{ $monitor->host() }}</div>
                    @php($series = $daily->get($monitor->id, []))
                    @php($peak = max(1, max($series ?: [0])))
                    <div class="mt-3 text-xs text-zinc-400"><span class="text-base text-zinc-100">{{ number_format($counts->get($monitor->id, 0)) }}</span> clicks · 30 days</div>
                    <div class="mt-2 flex h-10 items-end gap-px" role="img" aria-label="Clicks per day over the last 30 days">
                        @foreach($series as $clicks)
                            <div class="flex-1 {{ $clicks ? 'bg-indigo-500' : 'bg-zinc-800' }}" style="height: {{ $clicks ? max(8, round($clicks / $peak * 100)) : 4 }}%"></div>
                        @endforeach
                    </div>
                    @isset($thumbnails[$monitor->id])
                        <a href="{{ route('heatmaps.show', $monitor) }}" class="mt-3 block h-32 overflow-hidden border border-zinc-800"><img src="{{ $thumbnails[$monitor->id] }}" alt="Most clicked page of {{ $monitor->name }}" loading="lazy" class="w-full object-cover object-top"></a>
                    @endisset
                    @unless($counts->get($monitor->id))
                        <details class="mt-2 text-xs text-zinc-400">
                            <summary class="cursor-pointer">No clicks yet · tracker snippet</summary>
                            <p class="mt-1">Installed by Lore already; if a site needs it by hand, put this before <code>&lt;/head&gt;</code>:</p>
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
