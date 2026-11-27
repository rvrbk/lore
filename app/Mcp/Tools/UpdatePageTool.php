<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Models\Page;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Update a wiki page. Only the fields you pass change; pass parent_id null to move it to the top level, tags [] to clear tags. A change to title or body is kept as a revision. The body replaces the whole text, so get-page-tool first when editing.')]
#[IsIdempotent]
class UpdatePageTool extends Tool
{
    public function handle(Request $request): Response
    {
        $given = $request->all();
        $page = Page::find($request->get('id'));
        if ($page === null) {
            return Response::error('No page with that id.');
        }

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'body' => ['sometimes', 'nullable', 'string'],
            'parent_id' => ['sometimes', 'nullable', Rule::exists('pages', 'id')->where('space_id', $page->space_id), Rule::notIn($page->descendantIds())],
            'tags' => ['sometimes', 'nullable', 'array', 'max:'.Tag::MAX_PER_ITEM],
            'tags.*' => ['string', 'max:50'],
        ]);

        $page->fill(array_intersect_key($data, array_flip(['title', 'body', 'parent_id'])));
        if ($page->isDirty(['title', 'body'])) {
            $page->revisions()->create(['title' => $page->title, 'body' => $page->body ?? '']);
        }
        $page->save();
        if (array_key_exists('tags', $given)) {
            $page->syncTagNames($data['tags'] ?? null);
        }

        return Response::json(Presenter::page($page->refresh()->unsetRelation('tags'), withBody: true));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'title' => $schema->string(),
            'body' => $schema->string()->description('Full Markdown body; replaces the existing text.'),
            'parent_id' => $schema->integer()->nullable()->description('Page in the same space to nest under, or null for top level.'),
            'tags' => $schema->array()->items($schema->string())->description('Replaces the tags.'),
        ];
    }
}
