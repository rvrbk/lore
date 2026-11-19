<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UploadController extends Controller
{
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    /** Executable or scriptable types (html, svg, php, js) are deliberately not allowed. */
    private const EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'txt', 'md', 'csv', 'zip', 'docx', 'xlsx', 'pptx'];

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:'.implode(',', self::EXTENSIONS), 'mimes:'.implode(',', self::EXTENSIONS)],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $filename = Str::lower(Str::random(10)).'-'.Str::limit($base, 60, '').'.'.$extension;

        $file->storeAs('uploads', $filename, 'local');

        return response()->json([
            'url' => route('uploads.show', $filename),
            'name' => $file->getClientOriginalName(),
            'is_image' => in_array($extension, self::IMAGE_EXTENSIONS, true),
        ]);
    }

    /**
     * Files are private: they are only served to a logged-in user.
     */
    public function show(string $filename): StreamedResponse
    {
        abort_unless(preg_match('/^[a-z0-9][a-z0-9._-]*$/', $filename) === 1, 404);
        $path = 'uploads/'.$filename;
        abort_unless(Storage::disk('local')->exists($path), 404);

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $inline = in_array($extension, [...self::IMAGE_EXTENSIONS, 'pdf'], true);

        return Storage::disk('local')->response($path, $filename, [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ], $inline ? 'inline' : 'attachment');
    }
}
