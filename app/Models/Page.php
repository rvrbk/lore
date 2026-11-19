<?php

namespace App\Models;

use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasTags;

    /** @var array<string, array{label: string, title: string, body: string}> {{date}} is replaced when a page is created from a template */
    public const TEMPLATES = [
        'runbook' => [
            'label' => 'Runbook',
            'title' => 'Runbook: ',
            'body' => "## Purpose\n\nWhat this procedure is for and when to use it.\n\n## Prerequisites\n\n- [ ] Access to …\n\n## Steps\n\n1. …\n2. …\n\n## Verify\n\nHow to confirm it worked.\n\n## Rollback\n\nHow to undo it.\n",
        ],
        'meeting-notes' => [
            'label' => 'Meeting notes',
            'title' => 'Meeting {{date}}',
            'body' => "**Date:** {{date}}\n**Attendees:** \n\n## Agenda\n\n- \n\n## Notes\n\n\n\n## Decisions\n\n- \n\n## Action items\n\n- [ ] \n",
        ],
        'incident-report' => [
            'label' => 'Incident report',
            'title' => 'Incident {{date}}: ',
            'body' => "**Date:** {{date}}\n**Status:** Investigating\n**Impact:** \n\n## Timeline\n\n- hh:mm: \n\n## Current understanding\n\n\n\n## Actions taken\n\n- \n\n## Next steps\n\n- [ ] \n",
        ],
        'post-mortem' => [
            'label' => 'Post-mortem',
            'title' => 'Post-mortem: ',
            'body' => "## Summary\n\nWhat happened, in two sentences.\n\n## Impact\n\nWho or what was affected, and for how long.\n\n## Root cause\n\n\n\n## What went well\n\n- \n\n## What went wrong\n\n- \n\n## Follow-ups\n\n- [ ] \n",
        ],
    ];

    protected $fillable = ['space_id', 'parent_id', 'title', 'body', 'position'];

    protected function casts(): array
    {
        return [
            'pinned_at' => 'datetime',
            'viewed_at' => 'datetime',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('position')->orderBy('title');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->latest('id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /** Ids of this page and everything below it. */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $queue = [$this->id];
        while ($queue) {
            $children = static::whereIn('parent_id', $queue)->pluck('id')->all();
            $ids = array_merge($ids, $children);
            $queue = $children;
        }

        return $ids;
    }

    /** Ancestors from the root down to (not including) this page. */
    public function breadcrumbs(): array
    {
        $trail = [];
        $node = $this->parent;
        while ($node && count($trail) < 50) {
            array_unshift($trail, $node);
            $node = $node->parent;
        }

        return $trail;
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** Pages whose body links here with [[Title]] or [[Title|label]]. */
    public function backlinks(): Collection
    {
        $title = addcslashes($this->title, '%_\\');

        return static::with('space')
            ->whereKeyNot($this->id)
            ->where(fn ($q) => $q
                ->whereRaw("body like ? escape '\\'", ['%[['.$title.']]%'])
                ->orWhereRaw("body like ? escape '\\'", ['%[['.$title.'|%']))
            ->orderBy('title')
            ->get();
    }

    public function html(): string
    {
        return static::render($this->body ?? '', $this->space);
    }

    /**
     * Render Markdown. [[Title]] links resolve to pages (preferring the given space);
     * unknown titles become "create this page" links.
     */
    public static function render(string $markdown, ?Space $space = null): string
    {
        return Str::markdown(static::linkWikiTitles($markdown, $space), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    private static function linkWikiTitles(string $markdown, ?Space $space): string
    {
        $segments = preg_split('/(```.*?```|~~~.*?~~~|`[^`\n]*`)/s', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE);
        $pattern = '/\[\[([^\[\]|\n]{1,200})(?:\|([^\[\]\n]{1,200}))?\]\]/u';

        preg_match_all($pattern, implode('', array_filter($segments, fn ($i) => $i % 2 === 0, ARRAY_FILTER_USE_KEY)), $found);
        if ($found[1] === []) {
            return $markdown;
        }

        $titles = collect($found[1])->map(fn ($t) => mb_strtolower(trim($t)))->unique()->values();
        $pages = static::whereIn(DB::raw('lower(title)'), $titles->all())->get()
            ->sortBy(fn (Page $p) => $space && $p->space_id === $space->id ? 0 : 1)
            ->unique(fn (Page $p) => mb_strtolower($p->title));
        $byTitle = $pages->keyBy(fn (Page $p) => mb_strtolower($p->title));

        foreach ($segments as $i => $segment) {
            if ($i % 2 === 1) {
                continue;
            }
            $segments[$i] = preg_replace_callback($pattern, function (array $m) use ($byTitle, $space) {
                $title = trim($m[1]);
                $label = trim($m[2] ?? '') !== '' ? trim($m[2]) : $title;
                $label = str_replace(['[', ']'], ['\\[', '\\]'], $label);
                $page = $byTitle->get(mb_strtolower($title));

                if ($page) {
                    return '['.$label.']('.route('pages.show', $page).')';
                }

                return $space
                    ? '['.$label.']('.route('pages.create', ['space' => $space, 'title' => $title]).' "Page not found: click to create it")'
                    : $label;
            }, $segment);
        }

        return implode('', $segments);
    }

    public function snippet(string $needle, int $radius = 70): string
    {
        $text = strip_tags($this->body ?? '');
        $pos = mb_stripos($text, $needle);
        if ($pos === false) {
            return Str::limit($text, $radius * 2);
        }
        $start = max(0, $pos - $radius);

        return ($start > 0 ? '…' : '').trim(mb_substr($text, $start, $radius * 2 + mb_strlen($needle))).'…';
    }
}
