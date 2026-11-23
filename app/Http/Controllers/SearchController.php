<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\SiteSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request, SiteSearch $search): View
    {
        $q = trim((string) $request->query('q'));

        return view('search', ['q' => $q, 'results' => $search->search($q, 25)->groupBy('type')]);
    }

    /**
     * A few matches per kind of content, for the dropdown under the sidebar search field.
     */
    public function quick(Request $request, SiteSearch $search): JsonResponse
    {
        return response()->json($search->search((string) $request->query('q'), 3)->all());
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
