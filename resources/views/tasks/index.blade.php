<x-layouts.app title="Board">
    <h1 class="text-2xl font-semibold">Board</h1>
    <p class="mt-2 text-sm text-zinc-400">Drag cards between columns or reorder them. Click a card to edit it.</p>

    <div class="mt-6 grid items-start gap-4 lg:grid-cols-3" data-board data-move-url="{{ route('tasks.move', '__ID__') }}">
        @foreach(\App\Models\Task::STATUSES as $status => $label)
            @php($tasks = $columns->get($status, collect()))
            <section class="border border-zinc-800 bg-zinc-950/60 p-3" data-column="{{ $status }}">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">{{ $label }}</h2>
                    <span class="text-xs text-zinc-500" data-count>{{ $tasks->count() }}</span>
                </div>
                <ul class="min-h-16 space-y-2" data-dropzone>
                    @foreach($tasks as $task) @include('partials.task-card', ['task' => $task]) @endforeach
                </ul>
                <form method="POST" action="{{ route('tasks.store') }}" class="mt-3">
                    @csrf
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input name="title" placeholder="+ Add a task" required maxlength="200" aria-label="New task in {{ $label }}" class="w-full rounded-md border border-zinc-800 bg-transparent px-3 py-1.5 text-sm">
                </form>
            </section>
        @endforeach
    </div>
    @if($errors->any())<p class="mt-4 text-sm text-red-400">{{ $errors->first() }}</p>@endif

    <dialog data-task-dialog class="m-auto w-full max-w-lg border border-zinc-700 bg-zinc-900 p-6 text-zinc-200 backdrop:bg-black/70">
        <form method="POST" data-edit-form data-update-url="{{ route('tasks.update', '__ID__') }}" class="space-y-3">
            @csrf @method('PUT')
            <input name="title" required maxlength="200" placeholder="Title" class="w-full rounded-md border border-zinc-700 px-3 py-2 text-lg font-semibold">
            <textarea name="description" rows="5" maxlength="5000" placeholder="Description (optional)" class="w-full rounded-md border border-zinc-700 px-3 py-2 text-sm"></textarea>
            <div class="grid grid-cols-3 gap-3 text-sm">
                <label class="block"><span class="text-xs text-zinc-400">Status</span>
                    <select name="status" class="mt-1 w-full rounded-md border border-zinc-700 px-2 py-1.5">@foreach(\App\Models\Task::STATUSES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
                <label class="block"><span class="text-xs text-zinc-400">Priority</span>
                    <select name="priority" class="mt-1 w-full rounded-md border border-zinc-700 px-2 py-1.5">@foreach(\App\Models\Task::PRIORITIES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
                <label class="block"><span class="text-xs text-zinc-400">Due</span>
                    <input type="date" name="due_on" class="mt-1 w-full rounded-md border border-zinc-700 px-2 py-1.5"></label>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save</button>
                <button type="button" data-close class="px-4 py-2 text-sm text-zinc-400 hover:text-zinc-100">Cancel</button>
            </div>
        </form>
        <form method="POST" data-delete-form data-delete-url="{{ route('tasks.destroy', '__ID__') }}" onsubmit="return confirm('Delete this task?')" class="mt-4 border-t border-zinc-800 pt-3">
            @csrf @method('DELETE')<button class="text-sm text-red-400 underline">Delete task</button>
        </form>
    </dialog>
</x-layouts.app>
