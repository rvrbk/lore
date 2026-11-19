<?php

namespace App\Models;

use Database\Factories\DeploymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deployment extends Model
{
    /** @use HasFactory<DeploymentFactory> */
    use HasFactory;

    public const REQUESTED = 'requested';

    public const RUNNING = 'running';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    protected $attributes = ['status' => self::REQUESTED];

    protected $fillable = ['site', 'path', 'branch', 'status', 'from_commit', 'to_commit', 'output', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [self::REQUESTED, self::RUNNING]);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::REQUESTED, self::RUNNING], true);
    }
}
