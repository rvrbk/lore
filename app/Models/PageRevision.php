<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['page_id', 'title', 'body'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
