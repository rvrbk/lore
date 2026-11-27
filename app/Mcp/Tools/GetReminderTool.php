<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Models\Reminder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get one reminder.')]
#[IsReadOnly]
class GetReminderTool extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate(['id' => ['required', 'integer']]);

        $reminder = Reminder::find($data['id']);

        return $reminder ? Response::json(Presenter::reminder($reminder)) : Response::error('No reminder with that id.');
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->required()];
    }
}
