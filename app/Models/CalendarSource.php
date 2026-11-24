<?php

namespace App\Models;

use Database\Factories\CalendarSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An ICS feed (a Google "secret address in iCal format" or an iCloud public calendar) shown in the dashboard agenda.
 */
class CalendarSource extends Model
{
    /** @use HasFactory<CalendarSourceFactory> */
    use HasFactory;

    protected $fillable = ['name', 'url', 'color'];

    /** The cache key under which the raw feed is kept. */
    public function cacheKey(): string
    {
        return 'calendar.feed.'.$this->id;
    }
}
