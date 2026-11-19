@php($r = $reminder ?? null)
<form method="POST" action="{{ $r ? route('reminders.update', $r) : route('reminders.store') }}" class="grid gap-3 sm:grid-cols-2">
    @csrf
    @if($r) @method('PUT') @endif
    <input name="title" value="{{ old('title', $r?->title) }}" placeholder="Remind me to…" required class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm sm:col-span-2">
    <input type="datetime-local" name="due_at" value="{{ old('due_at', ($r?->due_at ?? now()->addDay()->setTime(9, 0))->format('Y-m-d\TH:i')) }}" required class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
    <select name="recurrence" class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
        @foreach(\App\Models\Reminder::RECURRENCES as $k => $label)
            <option value="{{ $k }}" @selected(old('recurrence', $r?->recurrence ?? 'none') === $k)>{{ $label }}</option>
        @endforeach
    </select>
    @isset($tasks)
        <select name="task_id" aria-label="Linked task" class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm sm:col-span-2">
            <option value="">No linked task</option>
            @foreach($tasks as $t)
                <option value="{{ $t->id }}" @selected(old('task_id', $r?->task_id) == $t->id)>{{ $t->title }}</option>
            @endforeach
        </select>
    @endisset
    @isset($pages)
        <select name="page_id" class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm sm:col-span-2">
            <option value="">No linked page</option>
            @foreach($pages as $p)
                <option value="{{ $p->id }}" @selected(old('page_id', $r?->page_id) == $p->id)>{{ $p->space->name }} / {{ $p->title }}</option>
            @endforeach
        </select>
    @else
        <input type="hidden" name="page_id" value="{{ $pageId ?? $r?->page_id }}">
    @endisset
    <textarea name="notes" rows="2" placeholder="Notes (optional)" class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm sm:col-span-2">{{ old('notes', $r?->notes) }}</textarea>
    @if($errors->any())<p class="text-sm text-red-400 sm:col-span-2">{{ $errors->first() }}</p>@endif
    <div class="sm:col-span-2"><button class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">{{ $r ? 'Save' : 'Add reminder' }}</button></div>
</form>
