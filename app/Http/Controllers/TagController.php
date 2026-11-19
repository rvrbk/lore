<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\View\View;

class TagController extends Controller
{
    public function index(): View
    {
        return view('tags.index', [
            'tags' => Tag::withCount(['pages', 'tasks'])->orderBy('name')->get(),
        ]);
    }

    public function show(Tag $tag): View
    {
        return view('tags.show', [
            'tag' => $tag,
            'pages' => $tag->pages()->with('space')->orderBy('title')->get(),
            'tasks' => $tag->tasks()->orderBy('status')->orderBy('position')->get(),
        ]);
    }
}
