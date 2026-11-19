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
}
