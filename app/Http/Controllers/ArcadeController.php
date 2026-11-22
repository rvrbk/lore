<?php

namespace App\Http\Controllers;

use App\Services\GoogleAnalytics;
use Illuminate\View\View;

class ArcadeController extends Controller
{
    /**
     * A small UFO shooter. The score to beat is the number of visitors your sites had this week.
     */
    public function __invoke(GoogleAnalytics $analytics): View
    {
        return view('arcade', ['target' => max(10, (int) collect($analytics->summary() ?? [])->sum('users'))]);
    }
}
