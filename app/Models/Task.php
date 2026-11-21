<?php

namespace App\Models;

use App\Models\Concerns\HasTags;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasTags;

    public const STATUSES = [
        'todo' => 'To do',
        'in_progress' => 'In progress',
        'done' => 'Done',
    ];

    public const PRIORITIES = [
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ];

    public const DONE_LIMIT = 10;

    protected $attributes = ['status' => 'todo', 'priority' => 'medium', 'recurrence' => 'none'];

    protected $fillable = ['title', 'description', 'status', 'priority', 'due_on', 'page_id', 'checklist', 'recurrence', 'completed_at', 'archived_at'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
            'checklist' => 'array',
        ];
    }

    /**
     * Tasks that are still on the Kanban board, as opposed to the archive.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeOnBoard(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function reminder(): HasOne
    {
        return $this->hasOne(Reminder::class)->whereNull('completed_at')->oldestOfMany('due_at');
    }

    /**
     * Set, move or clear the task's open reminder.
     */
    public function syncReminder(?string $remindAt): void
    {
        $open = Reminder::where('task_id', $this->id)->open();

        if ($remindAt === null || $remindAt === '') {
            $open->delete();

            return;
        }

        $existing = $open->orderBy('due_at')->first();
        if ($existing) {
            $existing->update(['title' => $this->title, 'due_at' => $remindAt, 'notified_at' => null]);

            return;
        }

        Reminder::create(['task_id' => $this->id, 'page_id' => $this->page_id, 'title' => $this->title, 'due_at' => $remindAt]);
    }

    /** @return array{done: int, total: int} */
    public function checklistProgress(): array
    {
        $items = collect($this->checklist ?? []);

        return ['done' => $items->where('done', true)->count(), 'total' => $items->count()];
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'done' && $this->due_on !== null && $this->due_on->isPast() && ! $this->due_on->isToday();
    }

    /**
     * Append a new task to the bottom of its column.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createAtBottom(array $attributes): self
    {
        $status = $attributes['status'] ?? 'todo';
        $attributes['position'] = (int) static::onBoard()->where('status', $status)->max('position') + 1;
        if ($status === 'done') {
            $attributes['completed_at'] = now();
        }

        $task = static::unguarded(fn () => static::create($attributes));
        static::archiveOverflow();

        return $task;
    }

    /**
     * Put this task in a column at a zero-based index and renumber the affected columns.
     */
    public function moveTo(string $status, int $index): void
    {
        DB::transaction(function () use ($status, $index) {
            $previous = $this->status;

            $ids = static::onBoard()->where('status', $status)->whereKeyNot($this->id)
                ->orderBy('position')->orderBy('id')->pluck('id')->all();
            array_splice($ids, min($index, count($ids)), 0, [$this->id]);

            $this->update([
                'status' => $status,
                'archived_at' => null,
                'completed_at' => $status === 'done' ? ($previous === 'done' ? $this->completed_at : now()) : null,
            ]);
            static::renumber($ids);

            if ($status === 'done' && $previous !== 'done') {
                Reminder::where('task_id', $this->id)->open()->update(['completed_at' => now()]);
                $this->spawnNextOccurrence();
            }

            if ($previous !== $status) {
                static::renumber(static::onBoard()->where('status', $previous)->orderBy('position')->orderBy('id')->pluck('id')->all());
            }

            static::archiveOverflow();
        });
    }

    /**
     * Keep only the most recently finished tasks in "Done"; the rest move to the archive.
     */
    public static function archiveOverflow(): void
    {
        $overflow = static::onBoard()->where('status', 'done')
            ->orderByDesc('completed_at')->orderByDesc('id')
            ->skip(self::DONE_LIMIT)->take(PHP_INT_MAX)->pluck('id');

        if ($overflow->isEmpty()) {
            return;
        }

        static::whereKey($overflow)->update(['archived_at' => now()]);
        static::renumber(static::onBoard()->where('status', 'done')->orderBy('position')->orderBy('id')->pluck('id')->all());
    }

    /**
     * A recurring task that is finished hands its schedule to a fresh copy in "To do".
     */
    private function spawnNextOccurrence(): void
    {
        if ($this->recurrence === 'none') {
            return;
        }

        $due = ($this->due_on ?? now())->copy()->startOfDay();
        $step = fn ($date) => match ($this->recurrence) {
            'daily' => $date->addDay(),
            'weekly' => $date->addWeek(),
            'monthly' => $date->addMonthNoOverflow(),
            default => $date->addYearNoOverflow(),
        };
        for ($guard = 0; $guard < 1000; $guard++) {
            $due = $step($due);
            if ($due->isFuture()) {
                break;
            }
        }

        $next = static::createAtBottom([
            'title' => $this->title,
            'description' => $this->description,
            'status' => 'todo',
            'priority' => $this->priority,
            'due_on' => $due,
            'page_id' => $this->page_id,
            'recurrence' => $this->recurrence,
            'checklist' => collect($this->checklist ?? [])->map(fn (array $item) => ['text' => $item['text'], 'done' => false])->all() ?: null,
        ]);
        $next->tags()->sync($this->tags->pluck('id')->all());

        $this->update(['recurrence' => 'none']);
    }

    /**
     * @param  array<int, int>  $ids
     */
    private static function renumber(array $ids): void
    {
        foreach ($ids as $position => $id) {
            static::whereKey($id)->update(['position' => $position]);
        }
    }
}
