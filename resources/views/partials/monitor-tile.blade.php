@php
    $state = match (true) {
        $monitor->is_up === null => ['label' => 'Pending', 'dot' => 'bg-zinc-500', 'text' => 'text-zinc-400', 'border' => 'border-zinc-800'],
        $monitor->is_up => ['label' => 'Up', 'dot' => 'bg-emerald-500', 'text' => 'text-emerald-400', 'border' => 'border-zinc-800'],
        default => ['label' => 'Down', 'dot' => 'bg-red-500', 'text' => 'text-red-400', 'border' => 'border-red-500/60'],
    };
@endphp
<li class="border bg-zinc-900 p-3 {{ $state['border'] }}">
    <div class="flex items-center justify-between gap-2">
        <span class="flex min-w-0 items-center gap-2">@include('partials.site-pet', ['monitor' => $monitor, 'openMonitorIds' => $openMonitorIds ?? []])<a href="{{ route('monitors.show', $monitor) }}" class="truncate font-medium text-zinc-100 hover:underline" title="{{ $monitor->name }}: uptime history">{{ $monitor->name }}</a></span>
        <span class="flex shrink-0 items-center gap-1.5 text-xs font-semibold uppercase {{ $state['text'] }}">
            <span class="size-2.5 {{ $state['dot'] }}"></span>{{ $state['label'] }}
        </span>
    </div>
    <a href="{{ $monitor->url }}" target="_blank" rel="noopener noreferrer" class="mt-0.5 block truncate text-xs text-zinc-400 hover:underline">{{ $monitor->host() }}</a>
    @isset($sparks)<div class="mt-2">@include('partials.sparkline', ['checks' => $sparks->get($monitor->id, collect())])</div>@endisset
    <div class="mt-3 flex items-end justify-between text-xs text-zinc-400">
        @if($monitor->is_up === null)
            <span>Not checked yet</span>
        @elseif($monitor->is_up)
            <span><span class="text-base text-zinc-100">{{ $monitor->response_ms }}</span> ms · HTTP {{ $monitor->status_code }}</span>
            <span title="Up since {{ $monitor->status_changed_at?->format('j M Y H:i') }}">up {{ $monitor->status_changed_at?->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</span>
        @else
            <span class="min-w-0 truncate text-red-400" title="{{ $monitor->error }}">{{ $monitor->error }}</span>
            <span class="shrink-0" title="Down since {{ $monitor->status_changed_at?->format('j M Y H:i') }}">down {{ $monitor->status_changed_at?->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</span>
        @endif
    </div>
    @if(($errorCounts[$monitor->id] ?? null) !== null)<div class="mt-1 text-xs {{ $errorCounts[$monitor->id] ? 'text-amber-300' : 'text-zinc-500' }}"><a href="{{ route('monitors.show', $monitor) }}" class="hover:underline">{{ $errorCounts[$monitor->id] }} logged {{ \Illuminate\Support\Str::plural('error', $errorCounts[$monitor->id]) }} in 24 h</a></div>@endif
    @if($monitor->checked_at)<div class="mt-1 text-xs text-zinc-500">checked {{ $monitor->checked_at->diffForHumans() }}</div>@endif
</li>
