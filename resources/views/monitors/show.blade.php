<x-layouts.app :title="$monitor->name">
    <a href="{{ route('monitors.index') }}" class="text-sm text-zinc-400 hover:underline">← Server health</a>
    <div class="mt-1 flex items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold">{{ $monitor->name }}</h1>
            <a href="{{ $monitor->url }}" target="_blank" rel="noopener noreferrer" class="block truncate text-sm text-zinc-400 hover:underline">{{ $monitor->url }}</a>
        </div>
        <form method="POST" action="{{ route('monitors.check-one', $monitor) }}">@csrf<button class="rounded border px-3 py-1.5 text-sm hover:bg-zinc-800">Check now</button></form>
    </div>

    <ul class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach($uptime as $label => $percent)
            <li class="border border-zinc-800 bg-zinc-900 p-3">
                <div class="text-xs uppercase text-zinc-400">Uptime · {{ $label }}</div>
                <div class="mt-1 text-xl {{ $percent === null ? 'text-zinc-500' : ($percent >= 99.5 ? 'text-emerald-400' : ($percent >= 95 ? 'text-amber-300' : 'text-red-400')) }}">{{ $percent === null ? '—' : $percent.'%' }}</div>
            </li>
        @endforeach
        <li class="border border-zinc-800 bg-zinc-900 p-3">
            <div class="text-xs uppercase text-zinc-400">Avg response · 24 h</div>
            <div class="mt-1 text-xl text-zinc-100">{{ $averageMs ? $averageMs.' ms' : '—' }}</div>
        </li>
    </ul>

    <section class="mt-8">
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-400">Last 24 hours</h2>
        @if($hours->isEmpty())
            <p class="text-sm text-zinc-400">No checks recorded yet. They appear once the scheduler has run.</p>
        @else
            <div class="flex h-8 items-stretch gap-0.5" role="img" aria-label="Uptime per hour over the last 24 hours">
                @foreach($hours as $hour)
                    <div class="flex-1 {{ $hour['up'] >= 100 ? 'bg-emerald-600' : ($hour['up'] >= 50 ? 'bg-amber-500' : 'bg-red-500') }}" title="{{ $hour['at']->format('D H:00') }}: {{ $hour['up'] }}% up"></div>
                @endforeach
            </div>
            <div class="mt-3 border border-zinc-800 bg-zinc-900 p-3">
                <div class="mb-1 text-xs text-zinc-400">Response time (ms) · {{ $checks->count() }} checks</div>
                @include('partials.sparkline', ['checks' => $checks, 'width' => 600, 'height' => 80, 'class' => 'h-20 w-full'])
            </div>
        @endif
    </section>

    <section class="mt-8">
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-400">Incidents · last 7 days</h2>
        <ul class="divide-y divide-zinc-800 border border-zinc-800 bg-zinc-900 text-sm">
            @forelse($incidents as $incident)
                <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                    <span class="text-red-400">Down since {{ $incident['from']->format('D j M, H:i') }}</span>
                    <span class="text-zinc-400">{{ $incident['to'] ? 'recovered '.$incident['to']->format('H:i').' · '.$incident['from']->diffForHumans($incident['to'], syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) : 'still down' }}</span>
                </li>
            @empty
                <li class="px-3 py-2 text-zinc-400">No downtime recorded. 🎉</li>
            @endforelse
        </ul>
    </section>
</x-layouts.app>
