<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Mcp\Tools\Concerns\TaskFields;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Update a Kanban task; only the fields you pass change. Pass status to move the card to another column (it lands at the bottom unless position is given), or position alone to reorder it within its column. Moving to done completes its reminder and spawns the next occurrence of a recurring task. Restores an archived task when moved.')]
#[IsIdempotent]
class UpdateTaskTool extends Tool
{
    use TaskFields;

    public function handle(Request $request): Response
    {
        $given = $request->all();
        $task = Task::find($request->get('id'));
        if ($task === null) {
            return Response::error('No task with that id.');
        }

        $data = $this->cleanChecklist($request->validate($this->fieldRules('sometimes') + ['position' => ['nullable', 'integer', 'min:0']]));

        $status = $data['status'] ?? $task->status;
        $position = $data['position'] ?? null;
        $skip = array_flip(['tags', 'remind_at', 'status', 'position']);

        $task->update(array_diff_key($data, $skip));
        if (array_key_exists('remind_at', $given)) {
            $task->syncReminder($data['remind_at'] ?? null);
        }
        if (array_key_exists('tags', $given)) {
            $task->syncTagNames($data['tags'] ?? null);
        }
        if ($status !== $task->status || $position !== null) {
            $task->moveTo($status, $position ?? PHP_INT_MAX);
        }

        return Response::json(Presenter::task($task->refresh()->unsetRelations()));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->required()]
            + $this->fieldSchema($schema)
            + ['position' => $schema->integer()->description('Zero-based index within the target column.')];
    }
}
