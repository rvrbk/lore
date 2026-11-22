<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * How the day went, from 1 (rough) to 5 (great).
 */
class Mood extends Model
{
    /** @var array<int, string> */
    public const FACES = [1 => '😫', 2 => '🙁', 3 => '😐', 4 => '🙂', 5 => '😄'];

    protected $fillable = ['day', 'level'];

    protected function casts(): array
    {
        return ['day' => 'date:Y-m-d'];
    }
}
