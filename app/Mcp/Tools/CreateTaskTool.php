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

#[Description('Add a card to the bottom of a Kanban column (default todo).')]
class CreateTaskTool extends Tool
{
    use TaskFields;

    public function handle(Request $request): Response
    {
        $data = $this->cleanChecklist($request->validate($this->fieldRules('required')));

        $task = Task::createAtBottom(array_diff_key($data, array_flip(['tags', 'remind_at'])));
        $task->syncTagNames($data['tags'] ?? null);
        if (filled($data['remind_at'] ?? null)) {
            $task->syncReminder($data['remind_at']);
        }

        return Response::json(Presenter::task($task->refresh()->unsetRelations()));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['title' => $schema->string()->required()] + $this->fieldSchema($schema);
    }
}
