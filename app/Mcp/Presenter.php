<?php

namespace App\Mcp;

use App\Models\Page;
use App\Models\Reminder;
use App\Models\Task;

/**
 * Turns Lore models into the plain arrays the MCP tools return.
 */
class Presenter
{
    /**
     * @return array<string, mixed>
     */
    public static function page(Page $page, bool $withBody = false): array
    {
        $page->loadMissing('space', 'tags');

        return [
            'id' => $page->id,
            'space' => $page->space->slug,
            'parent_id' => $page->parent_id,
            'title' => $page->title,
            'tags' => $page->tags->pluck('name')->all(),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ] + ($withBody ? ['body' => $page->body ?? ''] : []);
    }

    /**
     * @return array<string, mixed>
     */
    public static function task(Task $task): array
    {
        $task->loadMissing('tags', 'reminder');

        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'position' => $task->position,
            'priority' => $task->priority,
            'due_on' => $task->due_on?->toDateString(),
            'recurrence' => $task->recurrence,
            'page_id' => $task->page_id,
            'tags' => $task->tags->pluck('name')->all(),
            'checklist' => $task->checklist ?? [],
            'remind_at' => $task->reminder?->due_at->toIso8601String(),
            'archived' => $task->archived_at !== null,
            'completed_at' => $task->completed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function reminder(Reminder $reminder): array
    {
        return [
            'id' => $reminder->id,
            'title' => $reminder->title,
            'notes' => $reminder->notes,
            'due_at' => $reminder->due_at->toIso8601String(),
            'recurrence' => $reminder->recurrence,
            'page_id' => $reminder->page_id,
            'task_id' => $reminder->task_id,
            'overdue' => $reminder->isOverdue(),
            'completed_at' => $reminder->completed_at?->toIso8601String(),
        ];
    }
}
