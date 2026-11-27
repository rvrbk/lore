<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get one Kanban task.')]
#[IsReadOnly]
class GetTaskTool extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate(['id' => ['required', 'integer']]);

        $task = Task::find($data['id']);

        return $task ? Response::json(Presenter::task($task)) : Response::error('No task with that id.');
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->required()];
    }
}
