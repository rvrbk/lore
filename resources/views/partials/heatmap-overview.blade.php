<section class="mt-10">
<h2 id="clicks" class="text-lg font-semibold">Clicks</h2>
<p class="mt-1 text-sm text-zinc-400">Where and how often visitors click on each site. Every site sends its clicks to Lore through a small script in its layout; sites added above get it automatically. Open a site for its click map.</p>

    @php
        $allDays = collect(range(0, 29))->map(fn (int $i) => $daily->sum(fn (array $series) => $series[$i] ?? 0));
        $allPeak = max(1, (int) $allDays->max());
    @endphp
    <section class="mt-6 border border-zinc-800 bg-zinc-900 p-3">
        <div class="flex items-baseline justify-between text-xs uppercase text-zinc-400"><span>All sites · clicks per day</span><span><span class="text-base text-zinc-100">{{ number_format($allDays->sum()) }}</span> in 30 days</span></div>
        <div class="mt-3 flex h-28 items-end gap-px" role="img" aria-label="Clicks per day over the last 30 days, all sites">
            @foreach($allDays as $i => $clicks)
                <div class="flex-1 {{ $clicks ? 'bg-indigo-500' : 'bg-zinc-800' }}" style="height: {{ $clicks ? max(4, round($clicks / $allPeak * 100)) : 2 }}%" title="{{ now()->subDays(29 - $i)->format('D j M') }}: {{ $clicks }} clicks"></div>
            @endforeach
        </div>
        <div class="mt-1 flex justify-between text-xs text-zinc-500"><span>{{ now()->subDays(29)->format('j M') }}</span><span>peak {{ $allPeak }}/day</span><span>today</span></div>
    </section>

    @if($monitors->isNotEmpty())
        <ul class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($monitors as $monitor)
                <li class="border border-zinc-800 bg-zinc-900 p-3">
                    <a href="{{ route('analytics.heatmaps.show', $monitor) }}" class="truncate font-medium text-zinc-100 hover:underline">{{ $monitor->name }}</a>
                    <div class="truncate text-xs text-zinc-400">{{ $monitor->host() }}</div>
                    @php($series = $daily->get($monitor->id, []))
                    @php($peak = max(1, max($series ?: [0])))
                    <div class="mt-3 text-xs text-zinc-400"><span class="text-base text-zinc-100">{{ number_format($counts->get($monitor->id, 0)) }}</span> clicks · 30 days</div>
                    <div class="mt-2 flex h-10 items-end gap-px" role="img" aria-label="Clicks per day over the last 30 days">
                        @foreach($series as $clicks)
                            <div class="flex-1 {{ $clicks ? 'bg-indigo-500' : 'bg-zinc-800' }}" style="height: {{ $clicks ? max(8, round($clicks / $peak * 100)) : 4 }}%"></div>
                        @endforeach
                    </div>
                    @php($siteTops = $tops->get($monitor->id, []))
                    @foreach($siteTops as $top)
                        <div class="mt-3 text-xs">
                            <div class="flex justify-between gap-2 text-zinc-300"><span class="truncate" title="{{ $top['path'] }}">{{ $top['path'] }}</span><span class="shrink-0 text-zinc-400">{{ number_format($top['clicks']) }}</span></div>
                            @foreach($top['targets'] as $target)
                                <div class="mt-0.5 flex justify-between gap-2 pl-3 text-zinc-500">
                                    <span class="truncate" title="{{ $target['href'] }}">{{ $target['element'] }}@if($target['label']) “{{ $target['label'] }}”@endif @if($target['href'])→ {{ $target['href'] }}@endif</span>
                                    <span class="shrink-0">{{ number_format($target['clicks']) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
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
        <p class="mt-6 text-sm text-zinc-400">No sites yet. Add one above.</p>
    @endif
</section>
