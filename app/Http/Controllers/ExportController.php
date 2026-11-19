<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Space;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ExportController extends Controller
{
    public function space(Space $space): BinaryFileResponse
    {
        return $this->zip([$space], Str::slug($space->name).'.zip');
    }

    public function all(): BinaryFileResponse
    {
        return $this->zip(Space::orderBy('name')->get()->all(), 'lore-export-'.now()->format('Y-m-d').'.zip');
    }

    /**
     * @param  array<int, Space>  $spaces
     */
    private function zip(array $spaces, string $downloadName): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'lore-export-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $multiple = count($spaces) > 1;
        foreach ($spaces as $space) {
            $pages = $space->pages()->with('tags')->orderBy('position')->orderBy('title')->get()->groupBy(fn (Page $p) => $p->parent_id ?? '');
            $this->addLevel($zip, $pages, '', $multiple ? $this->safeName($space->name).'/' : '');
        }
        if ($zip->numFiles === 0) {
            $zip->addFromString('README.txt', "No pages to export.\n");
        }
        $zip->close();

        return response()->download($path, $downloadName, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    /**
     * A page becomes "Title.md"; its children go into a "Title/" folder beside it.
     *
     * @param  Collection<int|string, Collection<int, Page>>  $byParent
     */
    private function addLevel(ZipArchive $zip, Collection $byParent, int|string $parentId, string $dir): void
    {
        $used = [];
        foreach ($byParent->get($parentId, collect()) as $page) {
            $name = $this->safeName($page->title);
            for ($n = 2; isset($used[mb_strtolower($name)]); $n++) {
                $name = $this->safeName($page->title).' ('.$n.')';
            }
            $used[mb_strtolower($name)] = true;

            $zip->addFromString($dir.$name.'.md', $this->markdown($page));
            if ($byParent->has($page->id)) {
                $this->addLevel($zip, $byParent, $page->id, $dir.$name.'/');
            }
        }
    }

    private function markdown(Page $page): string
    {
        $front = "---\ntitle: ".json_encode($page->title, JSON_UNESCAPED_UNICODE)."\n";
        if ($page->tags->isNotEmpty()) {
            $front .= 'tags: ['.$page->tags->pluck('name')->implode(', ')."]\n";
        }
        $front .= 'updated: '.$page->updated_at->toDateString()."\n---\n\n";

        return $front.($page->body ?? '');
    }

    private function safeName(string $title): string
    {
        $name = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/u', '-', $title), ' .-');

        return Str::limit($name !== '' ? $name : 'untitled', 80, '');
    }
}
