<?php

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

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

    protected $attributes = ['status' => 'todo', 'priority' => 'medium'];

    protected $fillable = ['title', 'description', 'status', 'priority', 'due_on'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
        ];
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
        $attributes['position'] = (int) static::where('status', $status)->max('position') + 1;

        return static::unguarded(fn () => static::create($attributes));
    }

    /**
     * Put this task in a column at a zero-based index and renumber the affected columns.
     */
    public function moveTo(string $status, int $index): void
    {
        DB::transaction(function () use ($status, $index) {
            $previous = $this->status;

            $ids = static::where('status', $status)->whereKeyNot($this->id)
                ->orderBy('position')->orderBy('id')->pluck('id')->all();
            array_splice($ids, min($index, count($ids)), 0, [$this->id]);

            $this->update(['status' => $status]);
            static::renumber($ids);

            if ($previous !== $status) {
                static::renumber(static::where('status', $previous)->orderBy('position')->orderBy('id')->pluck('id')->all());
            }
        });
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
