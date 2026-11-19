<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Space;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarkdownController extends Controller
{
    /**
     * Render Markdown for the editor's live preview with the same rules as a saved page.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:500000'],
            'space' => ['nullable', 'string'],
        ]);

        return response()->json([
            'html' => Page::render($data['body'] ?? '', isset($data['space']) ? Space::where('slug', $data['space'])->first() : null),
        ]);
    }
}
