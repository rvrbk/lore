<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    public const DOWN = 'down';

    public const SMOKE_FAILED = 'smoke_failed';

    public const SSL_EXPIRING = 'ssl_expiring';

    public const DOMAIN_EXPIRING = 'domain_expiring';

    public const DISK_FULL = 'disk_full';

    public const MEMORY_HIGH = 'memory_high';

    /** @var array<string, string> */
    public const KINDS = [
        self::DOWN => 'Site down',
        self::SMOKE_FAILED => 'Failed deploy check',
        self::SSL_EXPIRING => 'SSL certificate expiring',
        self::DOMAIN_EXPIRING => 'Domain expiring',
        self::DISK_FULL => 'Disk nearly full',
        self::MEMORY_HIGH => 'Memory pressure',
    ];

    protected $fillable = ['monitor_id', 'deployment_id', 'task_id', 'page_id', 'kind', 'title', 'details', 'notes', 'started_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    /** How long the incident lasted, or has lasted so far. */
    public function duration(): string
    {
        return $this->started_at->diffForHumans($this->resolved_at ?? now(), CarbonInterface::DIFF_ABSOLUTE, true, 2);
    }
}
