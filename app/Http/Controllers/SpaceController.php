<?php

namespace App\Http\Controllers;

use App\Models\Space;
use Illuminate\Http\Request;

class SpaceController extends Controller
{
    public function create()
    {
        return view('spaces.form', ['space' => new Space]);
    }

    public function store(Request $request)
    {
        $space = Space::create($this->validated($request));

        return redirect()->route('spaces.show', $space);
    }

    public function show(Space $space)
    {
        return view('spaces.show', ['space' => $space, 'tree' => $space->tree()]);
    }

    public function edit(Space $space)
    {
        return view('spaces.form', ['space' => $space]);
    }

    public function update(Request $request, Space $space)
    {
        $space->update($this->validated($request));

        return redirect()->route('spaces.show', $space);
    }

    public function destroy(Space $space)
    {
        $space->delete();

        return redirect()->route('dashboard');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
