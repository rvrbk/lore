<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    /** @use HasFactory<ReminderFactory> */
    use BelongsToUser, HasFactory;

    public const RECURRENCES = [
        'none' => 'Does not repeat',
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
    ];

    protected $attributes = ['recurrence' => 'none'];

    protected $fillable = ['user_id', 'page_id', 'task_id', 'booking_id', 'title', 'notes', 'due_at', 'recurrence', 'notified_at', 'completed_at'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'notified_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    public function scopeDue(Builder $query): void
    {
        $query->open()->where('due_at', '<=', now());
    }

    public function isOverdue(): bool
    {
        return $this->completed_at === null && $this->due_at->isPast();
    }

    /** Finish the reminder; recurring ones roll forward to their next future occurrence instead. */
    public function complete(): void
    {
        if ($this->recurrence === 'none') {
            $this->update(['completed_at' => now()]);

            return;
        }

        $next = $this->due_at->copy();
        do {
            $next = match ($this->recurrence) {
                'daily' => $next->addDay(),
                'weekly' => $next->addWeek(),
                'monthly' => $next->addMonthNoOverflow(),
                'yearly' => $next->addYearNoOverflow(),
            };
        } while ($next->isPast());

        $this->update(['due_at' => $next, 'notified_at' => null]);
    }

    public function snooze(\DateTimeInterface $until): void
    {
        $this->update(['due_at' => $until, 'notified_at' => null, 'completed_at' => null]);
    }
}
