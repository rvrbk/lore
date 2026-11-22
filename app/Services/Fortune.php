<?php

namespace App\Services;

use App\Models\Page;
use Illuminate\Support\Facades\Cache;

/**
 * One line from your own wiki for the day: the same line all day, a different one tomorrow.
 */
class Fortune
{
    /**
     * @return array{page_id: int, title: string, line: string}|null
     */
    public function today(): ?array
    {
        $pick = Cache::remember('fortune.v1.'.today()->toDateString(), now()->addDay(), fn () => $this->pick() ?? false);

        return $pick ?: null;
    }

    /**
     * @return array{page_id: int, title: string, line: string}|null
     */
    private function pick(): ?array
    {
        $ids = Page::orderBy('id')->pluck('id')->all();
        if ($ids === []) {
            return null;
        }

        $seed = crc32(today()->toDateString());
        for ($attempt = 0; $attempt < count($ids) && $attempt < 20; $attempt++) {
            $page = Page::find($ids[($seed + $attempt * 7919) % count($ids)]);
            $lines = $page ? $this->lines($page->body) : [];
            if ($lines !== []) {
                return ['page_id' => $page->id, 'title' => $page->title, 'line' => $lines[$seed % count($lines)]];
            }
        }

        return null;
    }

    /**
     * Sentence-like lines: no headings, code fences, tables or bare links.
     *
     * @return array<int, string>
     */
    public function lines(?string $body): array
    {
        $lines = [];
        foreach (preg_split('/\R/', (string) $body) ?: [] as $line) {
            $line = trim(preg_replace('/^(\s*([-*>]|\d+\.)\s+)*(\[[ xX]\]\s*)?/', '', $line) ?? '');
            $line = trim(preg_replace('/[*_`]/', '', preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $line) ?? '') ?? '');
            if (mb_strlen($line) >= 25 && mb_strlen($line) <= 180 && ! str_starts_with($line, '#') && ! str_starts_with($line, '|') && ! str_starts_with($line, 'http') && preg_match('/\s/', $line)) {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
