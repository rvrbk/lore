<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $like = '%'.addcslashes($q, '%_\\').'%';

        $results = $q === '' ? collect() : Page::with('space')
            ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('body', 'like', $like))
            ->orderByRaw('case when title like ? then 0 else 1 end', [$like])
            ->latest('updated_at')
            ->limit(50)
            ->get();

        return view('search', ['q' => $q, 'results' => $results]);
    }
}
