<?php

namespace App\Mcp\Tools;

use App\Models\Space;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List the wiki spaces with their slugs and page counts. Pages are created inside a space, addressed by its slug.')]
#[IsReadOnly]
class ListSpacesTool extends Tool
{
    public function handle(Request $request): Response
    {
        return Response::json(['spaces' => Space::withCount('pages')->orderBy('name')->get()->map(fn (Space $space) => [
            'slug' => $space->slug,
            'name' => $space->name,
            'description' => $space->description,
            'pages' => $space->pages_count,
        ])->all()]);
    }
}
