<x-layouts.app :title="$incident->title">
    <a href="{{ route('incidents.index') }}" class="text-sm text-zinc-400 hover:underline">← Incidents</a>
    <h1 class="mt-1 text-2xl font-semibold">{{ $incident->title }}</h1>
    <p class="mt-1 text-sm text-zinc-400">{{ $incident->kindLabel() }} · {{ $incident->started_at->format('D j M H:i') }} · {{ $incident->isOpen() ? 'open for '.$incident->duration() : 'lasted '.$incident->duration() }}@if($incident->monitor) · <a href="{{ route('monitors.show', $incident->monitor) }}" class="hover:text-zinc-100 hover:underline">{{ $incident->monitor->name }}</a>@endif</p>
    @if($incident->details)<p class="mt-2 text-sm text-zinc-300">{{ $incident->details }}</p>@endif

    <section class="mt-6">
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-400">Timeline <span class="ml-2 normal-case">from {{ \App\Services\IncidentTimeline::LOOKBACK_HOURS }} hours before it started</span></h2>
        <ol class="divide-y divide-zinc-800 border border-zinc-800 bg-zinc-900 text-sm">
            @foreach($events as $event)
                <li class="flex gap-3 px-3 py-2">
                    <span class="w-28 shrink-0 text-xs tabular-nums text-zinc-400">{{ $event['at']->format('D j M H:i') }}</span>
                    <span class="shrink-0" aria-hidden="true">{{ $event['icon'] }}</span>
                    <span class="min-w-0 break-words text-zinc-200">@if($event['url'])<a href="{{ $event['url'] }}" class="hover:underline">{{ $event['text'] }}</a>@else{{ $event['text'] }}@endif</span>
                </li>
            @endforeach
        </ol>
        <p class="mt-2 text-xs text-zinc-500">Deploys, commits, failed checks, logged errors and page edits tagged with the site, in the order they happened.</p>
    </section>
</x-layouts.app>
