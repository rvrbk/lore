<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\Sql;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $like = '%'.addcslashes($q, '%_\\').'%';

        $operator = Sql::like();

        $results = $q === '' ? collect() : Page::with('space')
            ->where(fn ($w) => $w->where('title', $operator, $like)->orWhere('body', $operator, $like))
            ->orderByRaw("case when title {$operator} ? then 0 else 1 end", [$like])
            ->latest('updated_at')
            ->limit(50)
            ->get();

        return view('search', ['q' => $q, 'results' => $results]);
    }

    /**
     * Every page, for the Ctrl+K quick switcher.
     */
    public function palette(): JsonResponse
    {
        return response()->json(Page::with('space')->orderBy('title')->get()->map(fn (Page $page) => [
            'title' => $page->title,
            'space' => $page->space->name,
            'url' => route('pages.show', $page),
            'weather' => $page->weather(),
        ])->all());
    }
}
