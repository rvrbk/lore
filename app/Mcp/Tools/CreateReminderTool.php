<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Models\Reminder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a reminder. It is announced (and sent to Telegram if linked) when it comes due.')]
class CreateReminderTool extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'due_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'recurrence' => ['sometimes', Rule::in(array_keys(Reminder::RECURRENCES))],
            'page_id' => ['nullable', 'exists:pages,id'],
            'task_id' => ['nullable', 'exists:tasks,id'],
        ]);

        return Response::json(Presenter::reminder(Reminder::create($data)->refresh()));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'due_at' => $schema->string()->description('When it comes due, ISO 8601 with a UTC offset, e.g. 2026-12-01T14:00:00+01:00.')->required(),
            'notes' => $schema->string(),
            'recurrence' => $schema->string()->enum(array_keys(Reminder::RECURRENCES))->description('Default none.'),
            'page_id' => $schema->integer()->description('Wiki page it relates to.'),
            'task_id' => $schema->integer()->description('Kanban task it relates to.'),
        ];
    }
}
