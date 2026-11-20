<?php

use App\Http\Controllers\Api\NewsItemController;
use Illuminate\Support\Facades\Route;

Route::post('news', NewsItemController::class)->middleware('throttle:60,1')->name('api.news.store');
