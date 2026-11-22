<?php

namespace App\Models;

use Database\Factories\AnalyticsSiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsSite extends Model
{
    /** @use HasFactory<AnalyticsSiteFactory> */
    use HasFactory;

    protected $fillable = ['name', 'property_id'];

    /**
     * A site name reduced to lowercase letters and digits, so "verbeek.ug", "Verbeek-UG" and "verbeek ug" count as the same site.
     */
    public static function normalize(string $name): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower($name)) ?? '';
    }
}
