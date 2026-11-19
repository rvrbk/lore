<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Reminder;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $tag = $request->query('tag');

        return view('tasks.index', [
            'columns' => Task::with(['tags', 'page'])->withTag($tag)->orderBy('position')->orderBy('id')->get()->groupBy('status'),
            'activeTag' => $tag,
            'tags' => Tag::whereHas('tasks')->orderBy('name')->get(),
            'pages' => Page::with('space')->orderBy('title')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $task = Task::createAtBottom(Arr::except($data, 'tags'));
        $task->syncTagNames($data['tags'] ?? null);

        return redirect()->back(fallback: route('tasks.index'));
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $data = $this->validated($request);
        $status = $data['status'] ?? $task->status;
        unset($data['status']);

        $task->update(Arr::except($data, 'tags'));
        if (array_key_exists('tags', $data)) {
            $task->syncTagNames($data['tags']);
        }

        if ($status !== $task->status) {
            $task->moveTo($status, PHP_INT_MAX);
        }

        return redirect()->route('tasks.index');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        return redirect()->route('tasks.index');
    }

    /**
     * Drag-and-drop target: place the task in a column at a zero-based index.
     */
    public function move(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        $task->moveTo($data['status'], $data['position']);

        return response()->json(['status' => $task->status]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(array_keys(Task::STATUSES))],
            'priority' => ['sometimes', Rule::in(array_keys(Task::PRIORITIES))],
            'due_on' => ['nullable', 'date'],
            'page_id' => ['nullable', 'exists:pages,id'],
            'recurrence' => ['sometimes', Rule::in(array_keys(Reminder::RECURRENCES))],
            'tags' => ['nullable', 'string', 'max:300'],
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*.text' => ['nullable', 'string', 'max:200'],
            'checklist.*.done' => ['nullable', 'boolean'],
        ]);
        $data['due_on'] = $data['due_on'] ?? null;
        $data['page_id'] = $data['page_id'] ?? null;
        $data['checklist'] = collect($data['checklist'] ?? [])
            ->map(fn (array $item) => ['text' => trim((string) ($item['text'] ?? '')), 'done' => (bool) ($item['done'] ?? false)])
            ->filter(fn (array $item) => $item['text'] !== '')
            ->values()->all() ?: null;

        return $data;
    }
}
