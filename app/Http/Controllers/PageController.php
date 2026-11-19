<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Space;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    public function create(Request $request, Space $space)
    {
        $page = new Page(['space_id' => $space->id, 'parent_id' => $request->integer('parent') ?: null]);

        return view('pages.form', ['page' => $page, 'space' => $space, 'parents' => $this->parentOptions($space)]);
    }

    public function store(Request $request, Space $space)
    {
        $data = $this->validated($request, $space);
        $page = $space->pages()->create($data);
        $page->revisions()->create(['title' => $page->title, 'body' => $page->body]);

        return redirect()->route('pages.show', $page);
    }

    public function show(Page $page)
    {
        $page->load('space', 'children', 'reminders');

        return view('pages.show', [
            'page' => $page,
            'space' => $page->space,
            'tree' => $page->space->tree(),
            'reminders' => $page->reminders()->open()->orderBy('due_at')->get(),
        ]);
    }

    public function edit(Page $page)
    {
        return view('pages.form', [
            'page' => $page,
            'space' => $page->space,
            'parents' => $this->parentOptions($page->space, $page),
        ]);
    }

    public function update(Request $request, Page $page)
    {
        $data = $this->validated($request, $page->space, $page);
        $page->fill($data);

        if ($page->isDirty(['title', 'body'])) {
            $page->revisions()->create(['title' => $page->title, 'body' => $page->body]);
        }
        $page->save();

        return redirect()->route('pages.show', $page);
    }

    public function destroy(Page $page)
    {
        $space = $page->space;
        $page->delete();

        return redirect()->route('spaces.show', $space);
    }

    public function history(Page $page)
    {
        return view('pages.history', ['page' => $page, 'space' => $page->space, 'revisions' => $page->revisions()->get()]);
    }

    public function restore(Page $page, int $revision)
    {
        $rev = $page->revisions()->findOrFail($revision);
        $page->update(['title' => $rev->title, 'body' => $rev->body]);
        $page->revisions()->create(['title' => $rev->title, 'body' => $rev->body]);

        return redirect()->route('pages.show', $page);
    }

    private function validated(Request $request, Space $space, ?Page $page = null): array
    {
        $forbidden = $page ? $page->descendantIds() : [];

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string'],
            'parent_id' => [
                'nullable',
                Rule::exists('pages', 'id')->where('space_id', $space->id),
                Rule::notIn($forbidden),
            ],
        ]);
        $data['parent_id'] = $data['parent_id'] ?? null;

        return $data;
    }

    private function parentOptions(Space $space, ?Page $except = null): array
    {
        $byParent = $space->tree();
        $skip = $except?->exists ? $except->descendantIds() : [];
        $out = [];
        $walk = function ($parentKey, int $depth) use (&$walk, $byParent, $skip, &$out) {
            foreach ($byParent->get($parentKey, []) as $p) {
                if (in_array($p->id, $skip)) {
                    continue;
                }
                $out[$p->id] = str_repeat('— ', $depth).$p->title;
                $walk($p->id, $depth + 1);
            }
        };
        $walk('', 0);

        return $out;
    }
}
