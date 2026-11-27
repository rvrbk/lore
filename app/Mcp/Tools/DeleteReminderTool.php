<?php

namespace App\Mcp\Tools;

use App\Models\Reminder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Permanently delete a reminder. To just finish it, set completed with update-reminder-tool. Confirm with the user first.')]
#[IsDestructive]
class DeleteReminderTool extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate(['id' => ['required', 'integer']]);

        $reminder = Reminder::find($data['id']);
        if ($reminder === null) {
            return Response::error('No reminder with that id.');
        }

        $reminder->delete();

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
