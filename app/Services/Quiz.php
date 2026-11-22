<?php

namespace App\Services;

use App\Models\Page;

/**
 * Turns your own notes into fill-in-the-blank questions: a line with something worth remembering (a `code` term, a **bold** term or a number) has it blanked out.
 */
class Quiz
{
    /**
     * @return array{page_id: int, title: string, text: string, answer: string}|null
     */
    public function question(): ?array
    {
        $pages = Page::inRandomOrder()->limit(30)->get(['id', 'title', 'body']);
        foreach ($pages as $page) {
            $candidates = [];
            foreach (preg_split('/\R/', (string) $page->body) ?: [] as $line) {
                $line = trim(preg_replace('/^(\s*([-*>]|\d+\.)\s+)*(\[[ xX]\]\s*)?/', '', $line) ?? '');
                if (mb_strlen($line) < 25 || mb_strlen($line) > 200 || str_starts_with($line, '#') || str_starts_with($line, '|')) {
                    continue;
                }
                if (preg_match_all('/`([^`]{2,40})`|\*\*([^*]{2,40})\*\*|(?<![\w.])(\d{2,6})(?![\w.])/', $line, $matches, PREG_SET_ORDER)) {
                    $candidates[] = [$line, $matches[array_rand($matches)]];
                }
            }
            if ($candidates !== []) {
                [$line, $match] = $candidates[array_rand($candidates)];
                $answer = (string) ($match[1] !== '' ? $match[1] : ($match[2] !== '' ? $match[2] : ($match[3] ?? '')));

                return [
                    'page_id' => $page->id,
                    'title' => $page->title,
                    'text' => str_replace('⟦blank⟧', '_____', $this->plain(str_replace($match[0], '⟦blank⟧', $line))),
                    'answer' => $answer,
                ];
            }
        }

        return null;
    }

    public function isCorrect(string $given, string $answer): bool
    {
        $clean = fn (string $text): string => mb_strtolower(trim(preg_replace('/\s+/', ' ', $text) ?? ''));

        return $clean($given) !== '' && $clean($given) === $clean($answer);
    }

    private function plain(string $line): string
    {
        return trim(preg_replace('/[*_`]/', '', preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $line) ?? '') ?? '');
    }
}
