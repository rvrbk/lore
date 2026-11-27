<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreatePageTool;
use App\Mcp\Tools\CreateReminderTool;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\DeletePageTool;
use App\Mcp\Tools\DeleteReminderTool;
use App\Mcp\Tools\DeleteTaskTool;
use App\Mcp\Tools\GetPageTool;
use App\Mcp\Tools\GetReminderTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\ListPagesTool;
use App\Mcp\Tools\ListRemindersTool;
use App\Mcp\Tools\ListSpacesTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\UpdatePageTool;
use App\Mcp\Tools\UpdateReminderTool;
use App\Mcp\Tools\UpdateTaskTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Lore')]
#[Version('1.0.0')]
#[Instructions(<<<'TEXT'
Reads and writes the user's Lore workspace: wiki pages, the Kanban board and reminders.

Pages live in spaces (use list-spaces-tool to find a space slug) and hold Markdown; [[Title]] links to another page. Every body change is kept as a revision.

The Kanban board is the task list: columns are todo, in_progress and done. Only the 10 most recent done tasks stay on the board, older ones are archived. Use update-task-tool with status (and optionally position) to move a card.

Reminders have a due time and optionally repeat. Completing a recurring one rolls it forward to its next occurrence; completing a one-off keeps it in the completed list. They can be linked to a page or a task.

Deleting is permanent. Look a record up first and confirm with the user before deleting.
TEXT)]
class LoreServer extends Server
{
    protected array $tools = [
        ListSpacesTool::class,
        ListPagesTool::class,
        GetPageTool::class,
        CreatePageTool::class,
        UpdatePageTool::class,
        DeletePageTool::class,
        ListTasksTool::class,
        GetTaskTool::class,
        CreateTaskTool::class,
        UpdateTaskTool::class,
        DeleteTaskTool::class,
        ListRemindersTool::class,
        GetReminderTool::class,
        CreateReminderTool::class,
        UpdateReminderTool::class,
        DeleteReminderTool::class,
    ];
}
