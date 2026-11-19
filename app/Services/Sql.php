<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class Sql
{
    /**
     * The case-insensitive LIKE operator for the current database (SQLite's LIKE already ignores case, PostgreSQL's does not).
     */
    public static function like(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
