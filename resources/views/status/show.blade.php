<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => $monitor->name.' status'])
    <meta name="robots" content="noindex">
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-200 antialiased">
@php
    $state = match (true) {
        $monitor->is_up === null => ['label' => 'Status pending', 'dot' => 'bg-zinc-500', 'text' => 'text-zinc-300'],
        $monitor->is_up => ['label' => 'All systems operational', 'dot' => 'bg-emerald-500', 'text' => 'text-emerald-400'],
        default => ['label' => 'We are experiencing problems', 'dot' => 'bg-red-500', 'text' => 'text-red-400'],
    };
@endphp
<main class="mx-auto max-w-2xl p-4 sm:p-8">
    <h1 class="font-pixel text-base text-zinc-100">{{ $monitor->name }}</h1>
    <div class="mt-6 flex items-center gap-3 border border-zinc-800 bg-zinc-900 p-4">
        <span class="size-3 shrink-0 {{ $state['dot'] }}"></span>
        <span class="text-lg font-medium {{ $state['text'] }}">{{ $state['label'] }}</span>
    </div>
    @if($monitor->checked_at)<p class="mt-2 text-xs text-zinc-500">Last checked {{ $monitor->checked_at->diffForHumans() }}</p>@endif

    <section class="mt-8">
        <div class="mb-2 flex items-baseline justify-between text-sm">
            <h2 class="font-semibold text-zinc-300">Last {{ count($days) }} days</h2>
            <span class="text-zinc-400">{{ $uptime === null ? '—' : $uptime.'% uptime' }}</span>
        </div>
        <div class="flex h-10 items-stretch gap-0.5" role="img" aria-label="Uptime per day over the last {{ count($days) }} days">
            @foreach($days as $day)
                <div class="flex-1 {{ $day['percent'] === null ? 'bg-zinc-800' : ($day['percent'] >= 99.5 ? 'bg-emerald-600' : ($day['percent'] >= 95 ? 'bg-amber-500' : 'bg-red-500')) }}" title="{{ $day['date']->format('D j M') }}: {{ $day['percent'] === null ? 'no data' : $day['percent'].'% up' }}"></div>
            @endforeach
        </div>
    </section>

    <section class="mt-8">
        <h2 class="mb-2 text-sm font-semibold text-zinc-300">Past outages</h2>
        <ul class="divide-y divide-zinc-800 border border-zinc-800 bg-zinc-900 text-sm">
            @forelse($outages as $outage)
                <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                    <span>{{ $outage->started_at->format('D j M Y, H:i') }}</span>
                    <span class="{{ $outage->isOpen() ? 'text-red-400' : 'text-zinc-400' }}">{{ $outage->isOpen() ? 'ongoing for '.$outage->duration() : 'resolved after '.$outage->duration() }}</span>
                </li>
            @empty
                <li class="px-3 py-2 text-zinc-400">No outages in the last {{ count($days) }} days.</li>
            @endforelse
        </ul>
    </section>
</main>
</body>
</html>
