<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Models\Page;
use App\Services\Sql;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List or search wiki pages (without their bodies; use get-page-tool for the Markdown). Most recently edited first, at most 100.')]
#[IsReadOnly]
class ListPagesTool extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'space' => ['nullable', 'string'],
            'query' => ['nullable', 'string', 'max:200'],
            'tag' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $like = Sql::like();
        $needle = '%'.addcslashes($data['query'] ?? '', '%_\\').'%';

        $pages = Page::with('space', 'tags')
            ->when($data['space'] ?? null, fn ($q, $slug) => $q->whereHas('space', fn ($s) => $s->where('slug', $slug)))
            ->when($data['query'] ?? null, fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw("title {$like} ? escape '\\'", [$needle])
                ->orWhereRaw("body {$like} ? escape '\\'", [$needle])))
            ->withTag($data['tag'] ?? null)
            ->orderByDesc('updated_at')
            ->limit($data['limit'] ?? 100)
            ->get();

        return Response::json(['pages' => $pages->map(fn (Page $page) => Presenter::page($page))->all()]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'space' => $schema->string()->description('Only pages in the space with this slug.'),
            'query' => $schema->string()->description('Text to look for in titles and bodies.'),
            'tag' => $schema->string()->description('Only pages with this tag.'),
            'limit' => $schema->integer()->description('Maximum number of pages, 1 to 100.'),
        ];
    }
}
