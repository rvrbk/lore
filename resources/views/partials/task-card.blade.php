@php
    $priorityStyle = match ($task->priority) {
        'high' => 'text-red-400 border-red-500/50',
        'low' => 'text-zinc-400 border-zinc-700',
        default => 'text-amber-300 border-amber-400/40',
    };
    $progress = $task->checklistProgress();
@endphp
<li draggable="true" data-task-id="{{ $task->id }}" data-task="{{ json_encode(['id' => $task->id, 'title' => $task->title, 'description' => $task->description, 'status' => $task->status, 'priority' => $task->priority, 'due_on' => $task->due_on?->format('Y-m-d'), 'page_id' => $task->page_id, 'recurrence' => $task->recurrence, 'tags' => $task->tagList(), 'checklist' => $task->checklist ?? []]) }}"
    class="cursor-grab border border-zinc-700 bg-zinc-900 p-3 text-sm hover:border-zinc-500 active:cursor-grabbing">
    <div class="break-words font-medium text-zinc-100">{{ $task->title }}</div>
    @if($task->description)<p class="mt-1 line-clamp-2 text-xs text-zinc-400">{{ $task->description }}</p>@endif
    @if($task->tags->isNotEmpty())
        <div class="mt-2 flex flex-wrap gap-1.5">@foreach($task->tags as $tag)<a href="{{ route('tasks.index', ['tag' => $tag->name]) }}" class="text-xs text-indigo-300 hover:underline">#{{ $tag->name }}</a>@endforeach</div>
    @endif
    <div class="mt-2 flex flex-wrap items-center justify-between gap-x-2 gap-y-1 text-xs">
        <span class="flex items-center gap-2">
            <span class="border px-1.5 py-0.5 uppercase {{ $priorityStyle }}">{{ \App\Models\Task::PRIORITIES[$task->priority] }}</span>
            @if($progress['total'])<span class="{{ $progress['done'] === $progress['total'] ? 'text-emerald-400' : 'text-zinc-400' }}" title="Checklist">☑ {{ $progress['done'] }}/{{ $progress['total'] }}</span>@endif
            @if($task->recurrence !== 'none')<span class="text-zinc-400" title="Repeats {{ strtolower(\App\Models\Reminder::RECURRENCES[$task->recurrence]) }}">↻</span>@endif
        </span>
        @if($task->due_on)
            <span class="{{ $task->isOverdue() ? 'text-red-400' : 'text-zinc-400' }}" title="Due {{ $task->due_on->format('j M Y') }}">{{ $task->isOverdue() ? 'Overdue · ' : '' }}{{ $task->due_on->format('j M') }}</span>
        @endif
    </div>
    @if($task->page)
        <a href="{{ route('pages.show', $task->page) }}" class="mt-2 block truncate text-xs text-zinc-400 hover:text-zinc-100 hover:underline" title="{{ $task->page->title }}">↗ {{ $task->page->title }}</a>
    @endif
</li>
