@php
    $map = app(\App\Services\GoogleAnalytics::class)->worldMap();
    $totals = app(\App\Services\GoogleAnalytics::class)->countryTotals($analytics);
    $max = max(1, max($totals ?: [0]));
    $liveSites = app(\App\Services\GoogleAnalytics::class)->liveBySite();
    $live = $liveSites === null ? null : array_sum($liveSites);
    $top = array_slice($totals, 0, 6, true);
@endphp
@if($totals)
    <div class="border border-zinc-800 bg-zinc-900 p-3">
        <div class="flex items-baseline justify-between text-xs text-zinc-400">
            <span>Visitors by country
                <span data-live-users="{{ route('analytics.live') }}">@if($live !== null) · <span class="text-pink-400">● {{ number_format($live) }} live now</span>@foreach(array_filter($liveSites ?? []) as $name => $users) · <span class="text-zinc-300">{{ $name }} {{ number_format($users) }}</span>@endforeach @endif</span></span><span class="flex items-center gap-3">{{ count($totals) }} {{ \Illuminate\Support\Str::plural('country', count($totals)) }} · all sites
                <button type="button" data-map-sound aria-pressed="false" class="border border-zinc-700 bg-zinc-950 px-1 py-0.5 text-xs text-zinc-300 hover:bg-zinc-800">Sound off</button>
                <select data-map-style aria-label="Map style" class="border border-zinc-700 bg-zinc-950 px-1 py-0.5 text-xs text-zinc-300"></select></span>
        </div>
        <svg data-pixel-map data-news-url="{{ route('news.headlines') }}" viewBox="0 0 {{ $map['width'] }} {{ $map['height'] }}" class="mx-auto mt-2 h-auto w-full max-w-5xl" role="img" aria-label="World map of visitors by country">
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
