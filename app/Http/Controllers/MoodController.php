<?php

namespace App\Http\Controllers;

use App\Models\Mood;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MoodController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['level' => ['required', 'integer', 'between:1,5']]);
        Mood::updateOrCreate(['day' => today()->toDateString()], ['level' => $data['level']]);

        return redirect()->back(fallback: route('dashboard'));
    }
}
