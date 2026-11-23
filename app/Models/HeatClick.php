<?php

namespace App\Models;

use Database\Factories\HeatClickFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeatClick extends Model
{
    public const UPDATED_AT = null;

    public const KEEP_DAYS = 90;

    /** Clicks are stored with x in thousandths of the page width, so layouts of any width line up. */
    public const X_SCALE = 1000;

    public const MOBILE_BELOW_PX = 768;

    /** @use HasFactory<HeatClickFactory> */
    use HasFactory;

    protected $fillable = ['monitor_id', 'path', 'x', 'y', 'device', 'element', 'label', 'href'];

    /**
     * @return BelongsTo<Monitor, $this>
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
