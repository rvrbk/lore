<x-layouts.app :title="'#'.$tag->name">
    <a href="{{ route('tags.index') }}" class="text-sm text-zinc-400 hover:underline">← All tags</a>
    <h1 class="mt-1 text-2xl font-semibold">#{{ $tag->name }}</h1>

    <section class="mt-6">
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-400">Pages ({{ $pages->count() }})</h2>
        <ul class="divide-y divide-zinc-800 border border-zinc-800 bg-zinc-900 text-sm">
            @forelse($pages as $p)
                <li class="flex justify-between gap-3 px-3 py-2"><a href="{{ route('pages.show', $p) }}" class="font-medium hover:underline">{{ $p->title }}</a><span class="text-zinc-400">{{ $p->space->name }}</span></li>
            @empty
                <li class="px-3 py-2 text-zinc-400">No pages with this tag.</li>
            @endforelse
        </ul>
    </section>

    <section class="mt-8">
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-400">Tasks ({{ $tasks->count() }})</h2>
        <ul class="divide-y divide-zinc-800 border border-zinc-800 bg-zinc-900 text-sm">
            @forelse($tasks as $task)
                <li class="flex justify-between gap-3 px-3 py-2"><a href="{{ route('tasks.index', ['tag' => $tag->name]) }}" class="hover:underline">{{ $task->title }}</a><span class="text-zinc-400">{{ \App\Models\Task::STATUSES[$task->status] }}</span></li>
            @empty
                <li class="px-3 py-2 text-zinc-400">No tasks with this tag.</li>
            @endforelse
        </ul>
    </section>
</x-layouts.app>
