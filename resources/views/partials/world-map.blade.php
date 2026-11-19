@php
    $map = app(\App\Services\GoogleAnalytics::class)->worldMap();
    $totals = app(\App\Services\GoogleAnalytics::class)->countryTotals($analytics);
    $max = max(1, max($totals ?: [0]));
    $top = array_slice($totals, 0, 6, true);
@endphp
@if($totals)
    <div class="border border-zinc-800 bg-zinc-900 p-3">
        <div class="flex items-baseline justify-between text-xs text-zinc-400">
            <span>Visitors by country</span><span>{{ count($totals) }} {{ \Illuminate\Support\Str::plural('country', count($totals)) }} · all sites</span>
        </div>
        <svg viewBox="0 0 {{ $map['width'] }} {{ $map['height'] }}" class="mt-2 h-auto w-full" role="img" aria-label="World map of visitors by country">
            @foreach($map['countries'] as $code => $country)
                @php($users = $totals[$code] ?? 0)
                <path d="{{ $country['d'] }}" fill="{{ $users ? 'var(--color-indigo-400)' : 'var(--color-zinc-800)' }}" fill-opacity="{{ $users ? round(0.35 + 0.65 * sqrt($users / $max), 2) : 1 }}" stroke="var(--color-zinc-950)" stroke-width="0.25"><title>{{ $country['name'] }}{{ $users ? ': '.number_format($users).' '.\Illuminate\Support\Str::plural('user', $users) : '' }}</title></path>
            @endforeach
        </svg>
        <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-400">
            @foreach($top as $code => $users)
                <li><span class="text-zinc-100">{{ $map['countries'][$code]['name'] ?? $code }}</span> {{ number_format($users) }}</li>
            @endforeach
        </ul>
    </div>
@endif
