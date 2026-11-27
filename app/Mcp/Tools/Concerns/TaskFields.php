<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\Reminder;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;

/**
 * The task fields shared by the create and update tools.
 */
trait TaskFields
{
    /**
     * @return array<string, mixed>
     */
    protected function fieldRules(string $presence): array
    {
        return [
            'title' => [$presence, 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(array_keys(Task::STATUSES))],
            'priority' => ['sometimes', Rule::in(array_keys(Task::PRIORITIES))],
            'due_on' => ['nullable', 'date'],
            'page_id' => ['nullable', 'exists:pages,id'],
            'recurrence' => ['sometimes', Rule::in(array_keys(Reminder::RECURRENCES))],
            'tags' => ['nullable', 'array', 'max:'.Tag::MAX_PER_ITEM],
            'tags.*' => ['string', 'max:50'],
            'remind_at' => ['nullable', 'date'],
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*.text' => ['nullable', 'string', 'max:200'],
            'checklist.*.done' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function cleanChecklist(array $data): array
    {
        if (array_key_exists('checklist', $data)) {
            $data['checklist'] = collect($data['checklist'] ?? [])
                ->map(fn (array $item) => ['text' => trim((string) ($item['text'] ?? '')), 'done' => (bool) ($item['done'] ?? false)])
                ->filter(fn (array $item) => $item['text'] !== '')
                ->values()->all() ?: null;
        }

        return $data;
    }

    /**
     * @return array<string, Type>
     */
    protected function fieldSchema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string(),
            'description' => $schema->string()->nullable(),
            'status' => $schema->string()->enum(array_keys(Task::STATUSES))->description('Kanban column. Default todo.'),
            'priority' => $schema->string()->enum(array_keys(Task::PRIORITIES)),
            'due_on' => $schema->string()->nullable()->description('Due date, YYYY-MM-DD.'),
            'page_id' => $schema->integer()->nullable()->description('Wiki page this task belongs to.'),
            'recurrence' => $schema->string()->enum(array_keys(Reminder::RECURRENCES))->description('When a recurring task moves to done, a fresh copy appears in todo.'),
            'tags' => $schema->array()->items($schema->string()),
            'remind_at' => $schema->string()->nullable()->description('When to remind the user, ISO 8601. Null removes the reminder.'),
            'checklist' => $schema->array()->items($schema->object([
                'text' => $schema->string()->required(),
                'done' => $schema->boolean(),
            ]))->nullable(),
        ];
    }
}
