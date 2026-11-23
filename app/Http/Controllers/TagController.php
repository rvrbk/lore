<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use App\Models\Page;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
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

    /**
     * Candidate tags for the as-you-type suggestions: tags already in use, then site names, then page titles.
     */
    public function suggestions(): JsonResponse
    {
        $candidates = collect();

        foreach (Tag::orderBy('name')->pluck('name') as $name) {
            $candidates->push(['name' => $name, 'kind' => 'tag']);
        }

        foreach (Monitor::orderBy('name')->get() as $monitor) {
            $candidates->push(['name' => $monitor->tagName(), 'kind' => 'site']);
        }

        foreach (Page::orderBy('title')->pluck('title') as $title) {
            $candidates->push(['name' => Tag::normalize($title)[0] ?? '', 'kind' => 'page']);
        }

        return response()->json($candidates->filter(fn (array $candidate) => $candidate['name'] !== '')->unique('name')->values()->all());
    }
}
