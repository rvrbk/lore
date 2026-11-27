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

#[Description('List reminders, soonest due first. Defaults to the open ones; pass include_completed for the 20 most recently completed as well.')]
#[IsReadOnly]
class ListRemindersTool extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate(['include_completed' => ['nullable', 'boolean']]);

        $reminders = Reminder::open()->orderBy('due_at')->get();
        if ($data['include_completed'] ?? false) {
            $reminders = $reminders->concat(Reminder::whereNotNull('completed_at')->latest('completed_at')->limit(20)->get());
        }

        return Response::json(['reminders' => $reminders->map(fn (Reminder $reminder) => Presenter::reminder($reminder))->all()]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['include_completed' => $schema->boolean()];
    }
}
