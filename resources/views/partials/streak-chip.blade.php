@if($commitStreak['days'] > 0)
    <span class="shrink-0 border border-zinc-800 bg-zinc-900 px-1.5 py-1 text-xs tabular-nums {{ $commitStreak['at_risk'] ? 'text-red-400' : 'text-amber-300' }}" title="{{ $commitStreak['days'] }}-day commit streak{{ $commitStreak['at_risk'] ? '. No commit yet today, keep it alive!' : ($commitStreak['committed_today'] ? '. Committed today.' : '') }} Best in 90 days: {{ $commitStreak['best'] }}">🔥 {{ $commitStreak['days'] }}</span>
@endif
