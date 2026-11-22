<div class="truncate text-sm font-medium text-zinc-100">{{ $site['name'] }}</div>
@if($site['users'] === null)
    <p class="mt-2 text-xs text-red-400">Could not load. Is the service account added to this property?</p>
@else
    @php($impact = app(\App\Services\DeployImpact::class)->forTraffic($site['name'], $site['daily']))
    <div class="mt-2">@include('partials.trend', ['values' => $site['daily'], 'label' => 'Daily users for '.$site['name'], 'markers' => $impact['markers']])</div>
    @if($impact['markers'])
        <p class="mt-1 text-xs text-zinc-500"><span class="text-red-400">┆</span> deploy{{ count($impact['markers']) > 1 ? 's' : '' }}@if($impact['change']) · since {{ $impact['change']['deployed_at']->format('D H:i') }} users went from {{ $impact['change']['before'] }} to {{ $impact['change']['after'] }} a day (<span class="{{ $impact['change']['percent'] < -15 ? 'text-red-400' : ($impact['change']['percent'] > 15 ? 'text-emerald-400' : '') }}">{{ $impact['change']['percent'] > 0 ? '+' : '' }}{{ $impact['change']['percent'] }}%</span>)@endif</p>
    @endif
    <dl class="mt-3 grid grid-cols-3 gap-2 text-xs text-zinc-400">
        <div><dd class="text-xl font-semibold text-indigo-400">{{ number_format($site['users']) }}</dd><dt>Users</dt></div>
        <div><dd class="text-xl font-semibold text-zinc-100">{{ number_format($site['sessions']) }}</dd><dt>Sessions</dt></div>
        <div><dd class="text-xl font-semibold text-zinc-100">{{ number_format($site['views']) }}</dd><dt>Views</dt></div>
    </dl>
@endif
