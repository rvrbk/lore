<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Space;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    public function create(Request $request, Space $space)
    {
        $page = new Page(['space_id' => $space->id, 'parent_id' => $request->integer('parent') ?: null]);

        $template = Page::TEMPLATES[$request->query('template')] ?? null;
        $fill = fn (string $text) => str_replace('{{date}}', now()->format('Y-m-d'), $text);
        $page->title = $request->query('title') ?: ($template ? $fill($template['title']) : '');
        $page->body = $template ? $fill($template['body']) : '';

        return view('pages.form', [
            'page' => $page,
            'space' => $space,
            'parents' => $this->parentOptions($space),
            'template' => $request->query('template'),
            'templateTasks' => $template['tasks'] ?? [],
        ]);
    }

    public function store(Request $request, Space $space)
    {
        $data = $this->validated($request, $space);
        $page = $space->pages()->create(Arr::except($data, 'tags'));
        $page->revisions()->create(['title' => $page->title, 'body' => $page->body]);
        $page->syncTagNames($data['tags'] ?? null);

        if ($request->boolean('spawn_tasks')) {
            $this->spawnTemplateTasks($page, $request->input('template'), $data['tags'] ?? null);
        }

        return redirect()->route('pages.show', $page);
    }

    public function show(Page $page)
    {
        $page->load('space', 'children', 'reminders', 'tags');
        Page::withoutTimestamps(fn () => $page->forceFill(['viewed_at' => now()])->save());

        return view('pages.show', [
            'page' => $page,
            'space' => $page->space,
            'tree' => $page->space->tree(),
            'reminders' => $page->reminders()->open()->orderBy('due_at')->get(),
            'backlinks' => $page->backlinks(),
            'tasks' => $page->tasks()->where('status', '!=', 'done')->orderBy('position')->get(),
        ]);
    }

    public function edit(Page $page)
    {
        return view('pages.form', [
            'page' => $page->load('tags'),
            'space' => $page->space,
            'parents' => $this->parentOptions($page->space, $page),
            'template' => null,
            'templateTasks' => [],
        ]);
    }

    public function update(Request $request, Page $page)
    {
        $data = $this->validated($request, $page->space, $page);
        $page->fill(Arr::except($data, 'tags'));

        if ($page->isDirty(['title', 'body'])) {
            $page->revisions()->create(['title' => $page->title, 'body' => $page->body]);
        }
        $page->save();
        if (array_key_exists('tags', $data)) {
            $page->syncTagNames($data['tags']);
        }

        return redirect()->route('pages.show', $page);
    }

    public function pin(Page $page): RedirectResponse
    {
        Page::withoutTimestamps(fn () => $page->forceFill(['pinned_at' => $page->pinned_at ? null : now()])->save());

        return redirect()->back();
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
            'tags' => ['nullable', 'string', 'max:300'],
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

    /**
     * Put a checklist template's tasks on the Kanban board, linked to the new page and tagged like it.
     *
     * @param  string|array<int, string>|null  $tags
     */
    private function spawnTemplateTasks(Page $page, ?string $template, string|array|null $tags): void
    {
        foreach (Page::TEMPLATES[$template]['tasks'] ?? [] as $item) {
            $task = Task::createAtBottom([
                'title' => $item['title'],
                'priority' => $item['priority'],
                'due_on' => today()->addDays($item['days']),
                'page_id' => $page->id,
            ]);
            $task->syncTagNames($tags);
        }
    }
}
