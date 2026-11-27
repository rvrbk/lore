<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Models\Space;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a wiki page in a space. The body is Markdown; [[Title]] links to another page.')]
class CreatePageTool extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'space' => ['required', 'string', 'exists:spaces,slug'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string'],
            'tags' => ['nullable', 'array', 'max:'.Tag::MAX_PER_ITEM],
            'tags.*' => ['string', 'max:50'],
        ]);
        $space = Space::where('slug', $data['space'])->firstOrFail();

        $parent = $request->validate([
            'parent_id' => ['nullable', Rule::exists('pages', 'id')->where('space_id', $space->id)],
        ])['parent_id'] ?? null;

        $page = $space->pages()->create(['title' => $data['title'], 'body' => $data['body'] ?? '', 'parent_id' => $parent]);
        $page->revisions()->create(['title' => $page->title, 'body' => $page->body]);
        $page->syncTagNames($data['tags'] ?? null);

        return Response::json(Presenter::page($page->refresh(), withBody: true));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'space' => $schema->string()->description('Slug of the space, from list-spaces-tool.')->required(),
            'title' => $schema->string()->required(),
            'body' => $schema->string()->description('Markdown.'),
            'parent_id' => $schema->integer()->description('Id of a page in the same space to nest this one under.'),
            'tags' => $schema->array()->items($schema->string()),
        ];
    }
}
