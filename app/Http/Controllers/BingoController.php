<?php

namespace App\Http\Controllers;

use App\Services\Bingo;
use App\Services\GoogleAnalytics;
use Illuminate\View\View;

class BingoController extends Controller
{
    public function __invoke(Bingo $bingo, GoogleAnalytics $analytics): View
    {
        $visitors = $analytics->countryTotals($analytics->summary() ?? []);

        return view('bingo', ['card' => $bingo->card($visitors), 'hasData' => $visitors !== []]);
    }
}
