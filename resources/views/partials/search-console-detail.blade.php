<div class="border border-zinc-800 bg-zinc-900 p-3 text-sm">
    <div class="mb-1 text-xs text-zinc-400">Impressions per day (how often the site showed up in Google)</div>
    @include('partials.trend', ['values' => $report['daily_impressions'], 'label' => 'Search impressions per day', 'markers' => $report['impact']['markers']])
    @if($report['impact']['markers'])
        <p class="mt-1 text-xs text-zinc-500"><span class="text-red-400">┆</span> deploy{{ count($report['impact']['markers']) > 1 ? 's' : '' }}@if($report['impact']['change']) · since {{ $report['impact']['change']['deployed_at']->format('D j M H:i') }} impressions went from {{ $report['impact']['change']['before'] }} to {{ $report['impact']['change']['after'] }} a day (<span class="{{ $report['impact']['change']['percent'] < -25 ? 'text-red-400' : ($report['impact']['change']['percent'] > 25 ? 'text-emerald-400' : '') }}">{{ $report['impact']['change']['percent'] > 0 ? '+' : '' }}{{ $report['impact']['change']['percent'] }}%</span>)@endif</p>
    @endif
    <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-zinc-400 sm:grid-cols-4">
        <div><dd class="text-xl font-semibold text-indigo-400">{{ number_format($report['clicks']) }}</dd><dt>Clicks</dt></div>
        <div><dd class="text-xl font-semibold text-zinc-100">{{ number_format($report['impressions']) }}</dd><dt>Impressions</dt></div>
        <div><dd class="text-xl font-semibold text-zinc-100">{{ $report['ctr'] }}%</dd><dt>Clicked</dt></div>
        <div><dd class="text-xl font-semibold text-zinc-100">{{ $report['position'] ?? '—' }}</dd><dt>Average position</dt></div>
    </dl>
    <p class="mt-3 text-xs text-zinc-400">
        @if($report['home'])Home page: <span class="{{ $report['home']['verdict'] === 'PASS' ? 'text-emerald-400' : 'text-red-400' }}">{{ $report['home']['state'] ?: $report['home']['verdict'] }}</span>@endif
        @if($report['sitemap_problems'] !== null) · Sitemap errors: <span class="{{ $report['sitemap_problems'] ? 'text-red-400' : 'text-zinc-200' }}">{{ $report['sitemap_problems'] }}</span>@endif
    </p>
    @if($report['queries'])
        <table class="mt-3 w-full text-xs">
            <thead class="text-left text-zinc-500"><tr><th class="py-1 font-normal">Top searches</th><th class="font-normal">Clicks</th><th class="font-normal">Shown</th><th class="font-normal">Position</th></tr></thead>
            <tbody class="divide-y divide-zinc-800">@foreach($report['queries'] as $query)<tr><td class="max-w-0 truncate py-1 text-zinc-200">{{ $query['query'] }}</td><td>{{ $query['clicks'] }}</td><td>{{ $query['impressions'] }}</td><td>{{ $query['position'] }}</td></tr>@endforeach</tbody>
        </table>
    @endif
</div>
