<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day of a monitor's checks, kept for a year after the individual checks are pruned, for the uptime wall.
 */
class MonitorDay extends Model
{
    public $timestamps = false;

    protected $fillable = ['monitor_id', 'day', 'checks', 'up_checks'];

    protected function casts(): array
    {
        return ['day' => 'date:Y-m-d'];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
