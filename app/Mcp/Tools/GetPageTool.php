<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get one wiki page with its Markdown body and its child pages.')]
#[IsReadOnly]
class GetPageTool extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate(['id' => ['required', 'integer']]);

        $page = Page::with('children')->find($data['id']);
        if ($page === null) {
            return Response::error('No page with that id.');
        }

        return Response::json(Presenter::page($page, withBody: true) + [
            'children' => $page->children->map(fn (Page $child) => ['id' => $child->id, 'title' => $child->title])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->required()];
    }
}
