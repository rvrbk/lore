<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(): View
    {
        return view('tasks.index', [
            'columns' => Task::orderBy('position')->orderBy('id')->get()->groupBy('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Task::createAtBottom($this->validated($request));

        return redirect()->route('tasks.index');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $data = $this->validated($request);
        $status = $data['status'] ?? $task->status;
        unset($data['status']);

        $task->update($data);

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
        ]);
        $data['due_on'] = $data['due_on'] ?? null;

        return $data;
    }
}
