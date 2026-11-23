<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Page;
use App\Models\Reminder;
use App\Models\Space;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One search across everything on the site: pages, spaces, tasks, reminders, sites, incidents, tags and Pointy bookings.
 * Backs both the results page and the quick dropdown in the sidebar search field.
 */
class SiteSearch
{
    public const MIN_LENGTH = 2;

    /**
     * @return Collection<int, array{type: string, title: string, hint: string, url: string}>
     */
    public function search(string $query, int $perType = 5): Collection
    {
        $query = trim($query);
        if (mb_strlen($query) < self::MIN_LENGTH) {
            return collect();
        }

        $like = '%'.addcslashes($query, '%_\\').'%';

        return collect([
            $this->pages($query, $like, $perType),
            $this->spaces($like, $perType),
            $this->tasks($query, $like, $perType),
            $this->reminders($query, $like, $perType),
            $this->sites($like, $perType),
            $this->incidents($query, $like, $perType),
            $this->tags($like, $perType),
            $this->bookings($query, $like, $perType),
        ])->flatten(1)->values();
    }

    /**
     * @param  Builder<*>  $builder
     * @param  array<int, string>  $columns
     */
    private function matching(Builder $builder, array $columns, string $like): Builder
    {
        $operator = Sql::like();

        return $builder->where(function (Builder $where) use ($columns, $like, $operator): void {
            foreach ($columns as $column) {
                $where->orWhere($column, $operator, $like);
            }
        });
    }

    /**
     * Where the match sits in the text, so the dropdown shows why a row matched.
     */
    private function excerpt(?string $text, string $query, int $radius = 50): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $text)));
        $position = mb_stripos($text, $query);
        if ($position === false) {
            return Str::limit($text, $radius * 2);
        }
        $start = max(0, $position - $radius);

        return ($start > 0 ? '…' : '').trim(mb_substr($text, $start, $radius * 2 + mb_strlen($query))).(mb_strlen($text) > $start + $radius * 2 + mb_strlen($query) ? '…' : '');
    }

    /**
     * @return array<int, array{type: string, title: string, hint: string, url: string}>
     */
    private function pages(string $query, string $like, int $limit): array
    {
        $operator = Sql::like();

        return $this->matching(Page::with('space'), ['title', 'body'], $like)
            ->orderByRaw("case when title {$operator} ? then 0 else 1 end", [$like])
            ->latest('updated_at')->limit($limit)->get()
            ->map(fn (Page $page) => ['type' => 'Page', 'title' => $page->title, 'hint' => $page->space->name.' · '.$this->excerpt($page->body, $query), 'url' => route('pages.show', $page)])->all();
    }

    /**
     * @return array<int, array{type: string, title: string, hint: string, url: string}>
     */
    private function spaces(string $like, int $limit): array
    {
        return $this->matching(Space::query(), ['name', 'description'], $like)->orderBy('name')->limit($limit)->get()
            ->map(fn (Space $space) => ['type' => 'Space', 'title' => $space->name, 'hint' => Str::limit((string) $space->description, 100), 'url' => route('spaces.show', $space)])->all();
    }

    /**
     * @return array<int, array{type: string, title: string, hint: string, url: string}>
     */
    private function tasks(string $query, string $like, int $limit): array
    {
        return $this->matching(Task::query(), ['title', 'description'], $like)->orderByRaw('archived_at is not null')->latest('id')->limit($limit)->get()
            ->map(fn (Task $task) => [
                'type' => 'Task',
                'title' => $task->title,
                'hint' => ($task->archived_at !== null ? 'Archived' : Str::headline($task->status)).($task->description ? ' · '.$this->excerpt($task->description, $query) : ''),
                'url' => route($task->archived_at !== null ? 'tasks.archive' : 'tasks.index'),
            ])->all();
    }

    /**
     * @return array<int, array{type: string, title: string, hint: string, url: string}>
     */
    private function reminders(string $query, string $like, int $limit): array
    {
        return $this->matching(Reminder::query(), ['title', 'notes'], $like)->orderByRaw('completed_at is not null')->orderBy('due_at')->limit($limit)->get()
            ->map(fn (Reminder $reminder) => ['type' => 'Reminder', 'title' => $reminder->title, 'hint' => $reminder->due_at->format('j M Y H:i').($reminder->notes ? ' · '.$this->excerpt($reminder->notes, $query) : ''), 'url' => route('reminders.index')])->all();
    }

    /**
     * @return array<int, array{type: string, title: string, hint: string, url: string}>
     */
    private function sites(string $like, int $limit): array
    {
        return $this->matching(Monitor::query(), ['name', 'url'], $like)->orderBy('name')->limit($limit)->get()
            ->map(fn (Monitor $monitor) => ['type' => 'Site', 'title' => $monitor->name, 'hint' => $monitor->url, 'url' => route('monitors.show', $monitor)])->all();
    }

    /**
     * @return array<int, array{type: string, title: string, hint: string, url: string}>
     */
    private function incidents(string $query, string $like, int $limit): array
    {
        return $this->matching(Incident::query(), ['title', 'details', 'notes'], $like)->latest('started_at')->limit($limit)->get()
            ->map(fn (Incident $incident) => ['type' => 'Incident', 'title' => $incident->title, 'hint' => $incident->started_at->format('j M Y').' · '.$this->excerpt($incident->details ?? $incident->notes, $query), 'url' => route('incidents.show', $incident)])->all();
    }

    /**
     * @return array<int, array{type: string, title: string, hint: string, url: string}>
     */
    private function tags(string $like, int $limit): array
    {
        return $this->matching(Tag::withCount(['pages', 'tasks']), ['name'], $like)->orderBy('name')->limit($limit)->get()
            ->map(fn (Tag $tag) => ['type' => 'Tag', 'title' => $tag->name, 'hint' => $tag->pages_count.' pages · '.$tag->tasks_count.' tasks', 'url' => route('tags.show', $tag)])->all();
    }

    /**
     * @return array<int, array{type: string, title: string, hint: string, url: string}>
     */
    private function bookings(string $query, string $like, int $limit): array
    {
        $types = $this->matching(BookingType::query(), ['name', 'description'], $like)->orderBy('name')->limit($limit)->get()
            ->map(fn (BookingType $type) => ['type' => 'Booking type', 'title' => $type->name, 'hint' => Str::limit((string) $type->description, 100), 'url' => route('pointy.index')]);

        $bookings = $this->matching(Booking::query(), ['guest_name', 'guest_email', 'notes'], $like)->latest('starts_at')->limit($limit)->get()
            ->map(fn (Booking $booking) => ['type' => 'Booking', 'title' => $booking->guest_name, 'hint' => $booking->starts_at->format('j M Y H:i').' · '.$booking->guest_email.($booking->notes ? ' · '.$this->excerpt($booking->notes, $query) : ''), 'url' => route('pointy.index')]);

        return $types->concat($bookings)->all();
    }
}
