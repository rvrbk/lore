@php
    $latest = $metrics->last();
    $series = [
        'Disk used' => ['values' => $metrics->pluck('disk_used_percent'), 'unit' => '%', 'value' => $latest?->disk_used_percent],
        'Memory used' => ['values' => $metrics->pluck('memory_used_percent'), 'unit' => '%', 'value' => $latest?->memory_used_percent],
        'Load (1 min)' => ['values' => $metrics->pluck('load_average'), 'unit' => '', 'value' => $latest?->load_average],
        'Queue depth' => ['values' => $metrics->pluck('queue_depth'), 'unit' => '', 'value' => $latest?->queue_depth],
    ];
@endphp
<section class="mt-8">
    <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-400">Server resources <span class="ml-2 normal-case">last 48 hours</span></h2>
    @if($latest === null)
        <p class="text-sm text-zinc-400">No measurements yet. They are taken every 5 minutes while the scheduler runs (<code>php artisan server:measure</code> to take one now).</p>
    @else
        <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($series as $label => $item)
                <li class="border border-zinc-800 bg-zinc-900 p-3">
                    <div class="text-xs uppercase text-zinc-400">{{ $label }}</div>
                    <div class="mt-1 text-xl text-zinc-100">{{ $item['value'] === null ? '—' : $item['value'].$item['unit'] }}</div>
                    @if(($points = $item['values']->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v)->values())->count() > 1)<div class="mt-2">@include('partials.trend', ['values' => $points->nth(max(1, intdiv($points->count(), 48)))->all(), 'label' => $label.' over the last 48 hours'])</div>@endif
                </li>
            @endforeach
        </ul>
        <p class="mt-2 text-xs text-zinc-400">
            @if($diskDaysLeft !== null)<span class="{{ $diskDaysLeft <= 7 ? 'text-amber-300' : '' }}">At the current growth the disk is full in about {{ $diskDaysLeft }} day{{ $diskDaysLeft === 1 ? '' : 's' }}.</span> @endif
            @if($latest->failed_jobs)<span class="text-red-400">{{ $latest->failed_jobs }} failed job{{ $latest->failed_jobs === 1 ? '' : 's' }}.</span> @endif
            Alerts fire above {{ \App\Services\ServerHealth::DISK_ALERT_PERCENT }}% disk, {{ \App\Services\ServerHealth::MEMORY_ALERT_PERCENT }}% memory, or when the disk is on course to fill within {{ \App\Services\ServerHealth::DISK_FULL_WITHIN_DAYS }} days.
        </p>
    @endif
</section>
