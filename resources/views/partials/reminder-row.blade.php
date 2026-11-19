<li class="rounded-lg border bg-zinc-900 p-3 {{ $reminder->isOverdue() ? 'border-red-500/50' : 'border-zinc-800' }}">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 break-words">
            <div class="font-medium {{ $reminder->completed_at ? 'text-zinc-500 line-through' : '' }}">{{ $reminder->title }}</div>
            <div class="text-xs {{ $reminder->isOverdue() ? 'text-red-400' : 'text-zinc-400' }}">
                {{ $reminder->due_at->format('D j M Y, H:i') }}
                @if($reminder->recurrence !== 'none') · {{ strtolower(\App\Models\Reminder::RECURRENCES[$reminder->recurrence]) }} @endif
                @if($reminder->task) · <a href="{{ route('tasks.index') }}" class="underline">Task: {{ $reminder->task->title }}</a> @endif
                @if($reminder->page) · <a href="{{ route('pages.show', $reminder->page) }}" class="underline">{{ $reminder->page->title }}</a> @endif
            </div>
            @if($reminder->notes)<p class="mt-1 text-sm text-zinc-400">{{ $reminder->notes }}</p>@endif
        </div>
        <div class="flex flex-wrap gap-1.5 text-xs sm:shrink-0 sm:justify-end">
            @if($reminder->completed_at)
                <form method="POST" action="{{ route('reminders.reopen', $reminder) }}">@csrf<button class="rounded border px-2 py-1 hover:bg-zinc-800">Reopen</button></form>
            @else
                <form method="POST" action="{{ route('reminders.complete', $reminder) }}">@csrf<button class="rounded bg-emerald-600 px-2 py-1 text-white hover:bg-emerald-700">Done</button></form>
                @foreach(['1h' => '+1h', 'tomorrow' => 'Tomorrow', 'week' => 'Next week'] as $k => $label)
                    <form method="POST" action="{{ route('reminders.snooze', $reminder) }}">@csrf<input type="hidden" name="for" value="{{ $k }}"><button class="rounded border px-2 py-1 hover:bg-zinc-800">{{ $label }}</button></form>
                @endforeach
            @endif
            <form method="POST" action="{{ route('reminders.destroy', $reminder) }}" onsubmit="return confirm('Delete this reminder?')">@csrf @method('DELETE')<button class="rounded border px-2 py-1 text-red-400 hover:bg-red-500/10">Delete</button></form>
        </div>
    </div>
</li>
