<x-layouts.app title="Kanban archive">
    <h1 class="text-2xl font-semibold">Kanban archive</h1>
    <p class="mt-2 text-sm text-zinc-400">Finished cards that no longer fit in Done. <a href="{{ route('tasks.index') }}" class="underline">Back to the Kanban board</a>.</p>

    <ul class="mt-6 divide-y divide-zinc-800 border border-zinc-800 text-sm">
        @forelse($tasks as $task)
            <li class="flex items-center justify-between gap-3 px-3 py-2">
                <span class="min-w-0">
                    <span class="break-words text-zinc-100">{{ $task->title }}</span>
                    @foreach($task->tags as $tag)<a href="{{ route('tags.show', $tag) }}" class="ml-2 text-xs text-indigo-300 hover:underline">#{{ $tag->name }}</a>@endforeach
                    <span class="block text-xs text-zinc-400">Done {{ $task->completed_at?->format('j M Y') ?? '—' }}@if($task->page) · <a href="{{ route('pages.show', $task->page) }}" class="hover:underline">{{ $task->page->title }}</a>@endif</span>
                </span>
                <form method="POST" action="{{ route('tasks.restore', $task) }}">@csrf<button class="shrink-0 text-xs text-indigo-300 hover:underline">Restore to To do</button></form>
            </li>
        @empty
            <li class="px-3 py-4 text-zinc-400">Nothing archived yet.</li>
        @endforelse
    </ul>
</x-layouts.app>
