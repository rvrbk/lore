<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GraveyardController extends Controller
{
    /** Pages count as forgotten when nobody edited them for this long and nobody opened them for a while either. */
    public const EDITED_MONTHS = 6;

    public const VIEWED_MONTHS = 3;

    public function index(): View
    {
        return view('graveyard', [
            'pages' => Page::with('space')
                ->where('updated_at', '<', now()->subMonths(self::EDITED_MONTHS))
                ->where(fn ($query) => $query->whereNull('viewed_at')->orWhere('viewed_at', '<', now()->subMonths(self::VIEWED_MONTHS)))
                ->orderBy('updated_at')->get(),
        ]);
    }

    public function revive(Page $page): RedirectResponse
    {
        $page->touch();
        Page::withoutTimestamps(fn () => $page->forceFill(['viewed_at' => now()])->save());

        return redirect()->route('graveyard');
    }

    public function bury(Page $page): RedirectResponse
    {
        $page->delete();

        return redirect()->route('graveyard');
    }
}
