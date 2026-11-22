<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Page;
use App\Models\Space;
use App\Services\IncidentTimeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function index(): View
    {
        return view('incidents.index', [
            'open' => Incident::open()->with(['monitor', 'deployment', 'task', 'page'])->orderBy('started_at')->get(),
            'resolved' => Incident::whereNotNull('resolved_at')->with(['monitor', 'deployment', 'task', 'page'])->latest('resolved_at')->limit(30)->get(),
        ]);
    }

    public function show(Incident $incident, IncidentTimeline $timeline): View
    {
        return view('incidents.show', ['incident' => $incident->load(['monitor', 'deployment', 'task', 'page']), 'events' => $timeline->for($incident)]);
    }

    public function update(Request $request, Incident $incident): RedirectResponse
    {
        $incident->update($request->validate(['notes' => ['nullable', 'string', 'max:5000']]));

        return redirect()->back(fallback: route('incidents.index'));
    }

    /**
     * Start a post-mortem page for the incident, prefilled with what Lore knows about it.
     */
    public function postMortem(Incident $incident): RedirectResponse
    {
        if ($incident->page) {
            return redirect()->route('pages.show', $incident->page);
        }

        $space = Space::orderBy('id')->first() ?? Space::create(['name' => 'Operations']);
        $template = Page::TEMPLATES['post-mortem'];

        $facts = [
            '**What:** '.$incident->title,
            '**Started:** '.$incident->started_at->format('D j M Y H:i'),
            '**Resolved:** '.($incident->resolved_at?->format('D j M Y H:i') ?? 'still open'),
            '**Duration:** '.$incident->duration(),
        ];
        if ($incident->details) {
            $facts[] = '**Details:** '.$incident->details;
        }
        if ($incident->deployment) {
            $facts[] = '**Preceded by deploy #'.$incident->deployment->id.'** ('.$incident->deployment->site.', '.substr((string) $incident->deployment->to_commit, 0, 7).')';
        }
        if ($incident->notes) {
            $facts[] = "**Notes:**\n\n".$incident->notes;
        }

        $page = $space->pages()->create(['title' => 'Post-mortem: '.$incident->title, 'body' => implode("\n", $facts)."\n\n".$template['body']]);
        $page->revisions()->create(['title' => $page->title, 'body' => $page->body]);
        $page->syncTagNames(array_filter(['incident', $incident->monitor?->name]));
        $incident->update(['page_id' => $page->id]);

        return redirect()->route('pages.edit', $page);
    }
}
