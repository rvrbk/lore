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

#[Description('Update a reminder; only the fields you pass change. A new due_at (or any edit) makes it notify again. Set completed to true to finish it (a recurring reminder rolls forward to its next occurrence instead) or false to reopen it.')]
class UpdateReminderTool extends Tool
{
    public function handle(Request $request): Response
    {
        $given = $request->all();
        $reminder = Reminder::find($request->get('id'));
        if ($reminder === null) {
            return Response::error('No reminder with that id.');
        }

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'due_at' => ['sometimes', 'required', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'recurrence' => ['sometimes', Rule::in(array_keys(Reminder::RECURRENCES))],
            'page_id' => ['sometimes', 'nullable', 'exists:pages,id'],
            'task_id' => ['sometimes', 'nullable', 'exists:tasks,id'],
            'completed' => ['sometimes', 'boolean'],
        ]);

        $fields = array_diff_key($data, ['completed' => 1]);
        if ($fields !== []) {
            $reminder->update($fields + ['notified_at' => null]);
        }

        if (array_key_exists('completed', $given)) {
            if ($data['completed'] && $reminder->completed_at === null) {
                $reminder->complete();
            } elseif (! $data['completed']) {
                $reminder->update(['completed_at' => null]);
            }
        }

        return Response::json(Presenter::reminder($reminder->refresh()));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'title' => $schema->string(),
            'due_at' => $schema->string()->description('ISO 8601 with a UTC offset.'),
            'notes' => $schema->string()->nullable(),
            'recurrence' => $schema->string()->enum(array_keys(Reminder::RECURRENCES)),
            'page_id' => $schema->integer()->nullable(),
            'task_id' => $schema->integer()->nullable(),
            'completed' => $schema->boolean(),
        ];
    }
}
