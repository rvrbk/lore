@php
    $priorityStyle = match ($task->priority) {
        'high' => 'text-red-400 border-red-500/50',
        'low' => 'text-zinc-400 border-zinc-700',
        default => 'text-amber-300 border-amber-400/40',
    };
@endphp
<li draggable="true" data-task-id="{{ $task->id }}" data-task="{{ json_encode(['id' => $task->id, 'title' => $task->title, 'description' => $task->description, 'status' => $task->status, 'priority' => $task->priority, 'due_on' => $task->due_on?->format('Y-m-d')]) }}"
    class="cursor-grab border border-zinc-700 bg-zinc-900 p-3 text-sm hover:border-zinc-500 active:cursor-grabbing">
    <div class="break-words font-medium text-zinc-100">{{ $task->title }}</div>
    @if($task->description)<p class="mt-1 line-clamp-2 text-xs text-zinc-400">{{ $task->description }}</p>@endif
    <div class="mt-2 flex items-center justify-between gap-2 text-xs">
        <span class="border px-1.5 py-0.5 uppercase {{ $priorityStyle }}">{{ \App\Models\Task::PRIORITIES[$task->priority] }}</span>
        @if($task->due_on)
            <span class="{{ $task->isOverdue() ? 'text-red-400' : 'text-zinc-400' }}" title="Due {{ $task->due_on->format('j M Y') }}">{{ $task->isOverdue() ? 'Overdue · ' : '' }}{{ $task->due_on->format('j M') }}</span>
        @endif
    </div>
</li>
