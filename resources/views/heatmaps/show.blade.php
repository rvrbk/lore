<x-layouts.app :title="'Clicks · '.$monitor->name">
    <a href="{{ route('analytics.index') }}#clicks" class="text-sm text-zinc-400 hover:underline">← Analytics</a>
    <h1 class="mt-1 text-2xl font-semibold">{{ $monitor->name }}</h1>

    <form method="GET" class="mt-4 flex flex-wrap items-center gap-2 text-sm">
        <select name="days" onchange="this.form.submit()" aria-label="Period" class="rounded-md border border-zinc-700 px-2 py-1.5">
            @foreach($dayChoices as $choice)<option value="{{ $choice }}" @selected($choice === $days)>Last {{ $choice }} days</option>@endforeach
        </select>
    </form>

    @php
        $maxDaily = max(1, (int) $daily->max('clicks'));
        $totalClicks = (int) $daily->sum('clicks');
        $maxPage = max(1, (int) $topPages->max());
        $deviceTotal = max(1, (int) $devices->sum());
    @endphp
    <section class="mt-6 grid gap-3 lg:grid-cols-3">
        <div class="border border-zinc-800 bg-zinc-900 p-3 lg:col-span-2">
            <div class="flex items-baseline justify-between text-xs uppercase text-zinc-400"><span>Clicks per day</span><span><span class="text-base text-zinc-100">{{ number_format($totalClicks) }}</span> in {{ $days }} days</span></div>
            <div class="mt-3 flex h-28 items-end gap-px" role="img" aria-label="Clicks per day over the last {{ $days }} days">
                @foreach($daily as $point)
                    <div class="flex-1 {{ $point['clicks'] ? 'bg-indigo-500' : 'bg-zinc-800' }}" style="height: {{ $point['clicks'] ? max(4, round($point['clicks'] / $maxDaily * 100)) : 2 }}%" title="{{ $point['day']->format('D j M') }}: {{ $point['clicks'] }} clicks"></div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-xs text-zinc-500"><span>{{ $daily->first()['day']->format('j M') }}</span><span>peak {{ $maxDaily }}/day</span><span>{{ $daily->last()['day']->format('j M') }}</span></div>
        </div>
        <div class="border border-zinc-800 bg-zinc-900 p-3">
            <div class="text-xs uppercase text-zinc-400">Device</div>
            @forelse(['desktop' => 'Desktop', 'mobile' => 'Mobile'] as $key => $label)
                @php $count = (int) $devices->get($key, 0); @endphp
                <div class="mt-3 text-sm"><div class="flex justify-between"><span>{{ $label }}</span><span class="text-zinc-400">{{ number_format($count) }} · {{ round($count / $deviceTotal * 100) }}%</span></div>
                    <div class="mt-1 h-2 bg-zinc-800"><div class="h-2 {{ $key === 'mobile' ? 'bg-amber-400' : 'bg-indigo-500' }}" style="width: {{ $count / $deviceTotal * 100 }}%"></div></div></div>
            @empty
            @endforelse
        </div>
        <div class="border border-zinc-800 bg-zinc-900 p-3 lg:col-span-3">
            <div class="text-xs uppercase text-zinc-400">Most clicked pages</div>
            @forelse($topPages as $page => $count)
                <div class="mt-2 text-sm">
                    <div class="flex justify-between gap-3"><span class="truncate">{{ $page }}</span><span class="shrink-0 text-zinc-400">{{ number_format($count) }}</span></div>
                    <div class="mt-1 h-1.5 bg-zinc-800"><div class="h-1.5 bg-indigo-500" style="width: {{ $count / $maxPage * 100 }}%"></div></div>
                </div>
            @empty
                <p class="mt-2 text-sm text-zinc-400">No clicks in this period.</p>
            @endforelse
        </div>
    </section>
</x-layouts.app>
