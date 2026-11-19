<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReminderController extends Controller
{
    public function index()
    {
        return view('reminders.index', [
            'open' => Reminder::open()->with('page')->orderBy('due_at')->get(),
            'done' => Reminder::whereNotNull('completed_at')->with('page')->latest('completed_at')->limit(20)->get(),
            'pages' => Page::with('space')->orderBy('title')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Reminder::create($this->validated($request));

        return back();
    }

    public function update(Request $request, Reminder $reminder)
    {
        $reminder->update($this->validated($request) + ['notified_at' => null]);

        return back();
    }

    public function complete(Reminder $reminder)
    {
        $reminder->complete();

        return back();
    }

    public function reopen(Reminder $reminder)
    {
        $reminder->update(['completed_at' => null]);

        return back();
    }

    public function snooze(Request $request, Reminder $reminder)
    {
        $until = match ($request->input('for')) {
            '1h' => now()->addHour(),
            'tomorrow' => now()->addDay()->setTime(9, 0),
            'week' => now()->addWeek()->setTime(9, 0),
            default => abort(422),
        };
        $reminder->snooze($until);

        return back();
    }

    public function destroy(Reminder $reminder)
    {
        $reminder->delete();

        return back();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_at' => ['required', 'date'],
            'recurrence' => ['required', Rule::in(array_keys(Reminder::RECURRENCES))],
            'page_id' => ['nullable', 'exists:pages,id'],
        ]);
        $data['page_id'] = $data['page_id'] ?? null;

        return $data;
    }
}
