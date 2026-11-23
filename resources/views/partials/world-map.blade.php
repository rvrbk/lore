@php
    $map = app(\App\Services\GoogleAnalytics::class)->worldMap();
    $totals = app(\App\Services\GoogleAnalytics::class)->countryTotals($analytics);
    $max = max(1, max($totals ?: [0]));
    $liveSites = app(\App\Services\GoogleAnalytics::class)->liveBySite();
    $live = $liveSites === null ? null : array_sum($liveSites);
    $top = array_slice($totals, 0, 6, true);
@endphp
@if($totals)
    <div class="border border-zinc-800 bg-zinc-900 p-1.5 sm:p-3">
        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-xs text-zinc-400">
            <span>Visitors by country
                <span data-live-users="{{ route('analytics.live') }}">@if($live !== null) · <span class="text-pink-400">● {{ number_format($live) }} live now</span>@foreach(array_filter($liveSites ?? []) as $name => $users) · <span class="text-zinc-300">{{ $name }} {{ number_format($users) }}</span>@endforeach @endif</span></span><span class="ml-auto flex items-center gap-2">
                <span class="max-sm:hidden">{{ count($totals) }} {{ \Illuminate\Support\Str::plural('country', count($totals)) }} · all sites</span>
                <button type="button" data-map-sound aria-pressed="false" aria-label="Sound" class="flex h-7 w-7 items-center justify-center border border-zinc-700 bg-zinc-950 text-zinc-400 hover:bg-zinc-800 hover:text-pink-400 aria-pressed:border-pink-500/60 aria-pressed:text-pink-400 sm:h-auto sm:w-auto sm:px-1 sm:py-0.5 sm:text-xs sm:text-zinc-300"><span data-sound-icon class="sm:hidden" aria-hidden="true"><svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M4 9v6h4l5 4V5L8 9z"/><path data-sound-waves d="M16 9a4 4 0 0 1 0 6M19 6a8 8 0 0 1 0 12"/><path data-sound-off d="M16 9l5 6M21 9l-5 6"/></svg></span><span data-sound-text class="hidden sm:inline">Sound off</span></button>
                <span class="relative flex h-7 w-7 items-center justify-center border border-zinc-700 bg-zinc-950 text-zinc-400 focus-within:text-pink-400 sm:h-auto sm:w-auto sm:border-0 sm:bg-transparent sm:text-xs sm:text-zinc-300"><span class="pointer-events-none sm:hidden" aria-hidden="true"><svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M12 3a9 9 0 1 0 0 18c1.5 0 2-1 1.5-2s0-2 1.5-2h2a3 3 0 0 0 3-3c0-5-4-11-8-11z"/><path d="M7.5 11h.01M10 7.5h.01M14.5 7.5h.01"/></svg></span><select data-map-style aria-label="Map style" class="max-sm:absolute max-sm:inset-0 max-sm:h-full max-sm:w-full max-sm:opacity-0 border border-zinc-700 bg-zinc-950 px-1 py-0.5 text-xs text-zinc-300"></select></span></span>
        </div>
        <svg data-pixel-map data-news-url="{{ route('news.headlines') }}" data-live-url="{{ route('analytics.live') }}" viewBox="0 0 {{ $map['width'] }} {{ $map['height'] }}" class="mx-auto mt-2 h-auto w-full max-w-5xl" role="img" aria-label="World map of visitors by country">
            @foreach($map['countries'] as $code => $country)
                @php($users = $totals[$code] ?? 0)
                <path data-code="{{ $code }}" d="{{ $country['d'] }}" fill="{{ $users ? 'var(--color-indigo-400)' : 'var(--color-zinc-800)' }}" fill-opacity="{{ $users ? round(0.35 + 0.65 * sqrt($users / $max), 2) : 1 }}" stroke="var(--color-zinc-950)" stroke-width="0.25"><title>{{ $country['name'] }}{{ $users ? ': '.number_format($users).' '.\Illuminate\Support\Str::plural('user', $users) : '' }}</title></path>
            @endforeach
        </svg>
        <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-400">
            @foreach($top as $code => $users)
                <li><span class="text-zinc-100">{{ $map['countries'][$code]['name'] ?? $code }}</span> {{ number_format($users) }}</li>
            @endforeach
        </ul>
    </div>
@endif
