<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Reminder;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;

class SurpriseController extends Controller
{
    /**
     * Opens something at random: a page, or the page behind a forgotten task or reminder.
     */
    public function __invoke(): RedirectResponse
    {
        $task = Task::onBoard()->where('status', '!=', 'done')->whereNotNull('page_id')->inRandomOrder()->first();
        $reminder = Reminder::open()->whereNotNull('page_id')->inRandomOrder()->first();

        $options = collect([Page::inRandomOrder()->first(), $task?->page, $reminder?->page])->filter()->values();

        return $options->isEmpty() ? redirect()->route('dashboard') : redirect()->route('pages.show', $options->random());
    }
}
