<?php

namespace App\Mcp\Tools;

use App\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Permanently delete a wiki page and its revisions; its child pages move to the top level. Confirm with the user first.')]
#[IsDestructive]
class DeletePageTool extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate(['id' => ['required', 'integer']]);

        $page = Page::find($data['id']);
        if ($page === null) {
            return Response::error('No page with that id.');
        }

        $page->delete();

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
