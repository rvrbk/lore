<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List the cards on the Kanban board in column order, optionally filtered. Archived (old done) tasks are left out unless asked for.')]
#[IsReadOnly]
class ListTasksTool extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Task::STATUSES))],
            'tag' => ['nullable', 'string', 'max:50'],
            'page_id' => ['nullable', 'integer'],
            'include_archived' => ['nullable', 'boolean'],
        ]);

        $tasks = Task::with('tags', 'reminder')
            ->unless($data['include_archived'] ?? false, fn (Builder $q) => $q->onBoard())
            ->when($data['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($data['page_id'] ?? null, fn (Builder $q, int $id) => $q->where('page_id', $id))
            ->withTag($data['tag'] ?? null)
            ->orderBy('position')->orderBy('id')
            ->get();

        return Response::json(['tasks' => $tasks->map(fn (Task $task) => Presenter::task($task))->all()]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(array_keys(Task::STATUSES)),
            'tag' => $schema->string(),
            'page_id' => $schema->integer()->description('Only tasks linked to this page.'),
            'include_archived' => $schema->boolean(),
        ];
    }
}
