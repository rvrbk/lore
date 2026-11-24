<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NavVisit extends Model
{
    protected $fillable = ['user_id', 'item', 'visits'];
}
