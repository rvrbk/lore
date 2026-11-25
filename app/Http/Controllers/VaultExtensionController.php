<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * The install page for the Chrome extension, and the extension as a zip. Chrome only installs extensions from outside its store as an unpacked folder,
 * so this is the folder, zipped, with the install steps on the vault page. It holds code only, never anyone's data.
 */
class VaultExtensionController extends Controller
{
    public function show(): View
    {
        return view('vault.extension');
    }

    public function download(): BinaryFileResponse
    {
        $source = base_path('chrome-extension');
        abort_unless(is_dir($source), 404);

        $path = tempnam(sys_get_temp_dir(), 'lore-extension-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach (Finder::create()->files()->in($source) as $file) {
            $zip->addFile($file->getRealPath(), 'lore-vault-extension/'.$file->getRelativePathname());
        }
        $zip->close();

        return response()->download($path, 'lore-vault-extension.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }
}
