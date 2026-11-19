<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerMetric extends Model
{
    public $timestamps = false;

    public const KEEP_HISTORY_DAYS = 30;

    protected $fillable = ['recorded_at', 'disk_used_percent', 'disk_free_bytes', 'memory_used_percent', 'load_average', 'queue_depth', 'failed_jobs'];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'disk_used_percent' => 'float',
            'memory_used_percent' => 'float',
            'load_average' => 'float',
        ];
    }
}
