<article class="mb-3 border bg-zinc-900 p-3 text-sm {{ $incident->isOpen() ? 'border-red-500/60' : 'border-zinc-800' }}">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
            <div class="font-medium text-zinc-100">{{ $incident->title }}</div>
            <div class="mt-0.5 text-xs text-zinc-400">
                {{ $incident->kindLabel() }} · {{ $incident->started_at->format('D j M H:i') }}
                · {{ $incident->isOpen() ? 'open for '.$incident->duration() : 'lasted '.$incident->duration() }}
            </div>
        </div>
        <span class="shrink-0 border px-1.5 py-0.5 text-xs font-semibold uppercase {{ $incident->isOpen() ? 'border-red-500/50 text-red-400' : 'border-emerald-500/40 text-emerald-400' }}">{{ $incident->isOpen() ? 'Open' : 'Resolved' }}</span>
    </div>
    @if($incident->details)<p class="mt-2 text-zinc-300">{{ $incident->details }}</p>@endif
    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-400">
        @if($incident->monitor)<a href="{{ route('monitors.show', $incident->monitor) }}" class="hover:text-zinc-100 hover:underline">↗ {{ $incident->monitor->name }}</a>@endif
        @if($incident->deployment)<a href="{{ route('deploy.index') }}" class="hover:text-zinc-100 hover:underline" title="Deploy #{{ $incident->deployment->id }} finished {{ $incident->deployment->finished_at?->diffForHumans() }}">↗ after deploy #{{ $incident->deployment->id }}</a>@endif
        <a href="{{ route('incidents.show', $incident) }}" class="text-indigo-300 hover:underline">↗ timeline</a>
        @if($incident->task)<a href="{{ route('tasks.index') }}" class="hover:text-zinc-100 hover:underline">↗ Kanban card</a>@endif
        @if($incident->page)<a href="{{ route('pages.show', $incident->page) }}" class="hover:text-zinc-100 hover:underline">↗ post-mortem</a>@endif
    </div>
    <form method="POST" action="{{ route('incidents.update', $incident) }}" class="mt-3 flex gap-2">
        @csrf @method('PUT')
        <textarea name="notes" rows="2" maxlength="5000" placeholder="Notes: what you found, what you did…" aria-label="Notes" class="min-w-0 flex-1 rounded-md border border-zinc-700 px-2 py-1 text-sm">{{ $incident->notes }}</textarea>
        <button class="self-start rounded border px-2 py-1 text-xs hover:bg-zinc-800">Save notes</button>
    </form>
    @unless($incident->page)
        <form method="POST" action="{{ route('incidents.post-mortem', $incident) }}" class="mt-2">@csrf
            <button class="text-xs text-indigo-300 hover:underline">Write a post-mortem page from this incident</button>
        </form>
    @endunless
</article>
