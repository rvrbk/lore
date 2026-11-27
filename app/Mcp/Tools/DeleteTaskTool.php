<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Permanently delete a Kanban task. Moving it to done is usually what is meant instead. Confirm with the user first.')]
#[IsDestructive]
class DeleteTaskTool extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate(['id' => ['required', 'integer']]);

        $task = Task::find($data['id']);
        if ($task === null) {
            return Response::error('No task with that id.');
        }

        $task->delete();

        return Response::json(['deleted' => $data['id']]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->required()];
    }
}
