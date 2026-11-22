<?php

use App\Http\Controllers\Api\DeployHookController;
use App\Http\Controllers\Api\HeatClickController;
use App\Http\Controllers\Api\NewsItemController;
use Illuminate\Support\Facades\Route;

Route::post('news', NewsItemController::class)->middleware('throttle:60,1')->name('api.news.store');
Route::post('deploy/{monitor}', DeployHookController::class)->middleware('throttle:30,1')->name('api.deploy.hook');
Route::post('heat', HeatClickController::class)->middleware('throttle:300,1')->name('api.heat.store');
