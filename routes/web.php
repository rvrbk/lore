<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SpaceController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/search', SearchController::class)->name('search');

    Route::resource('spaces', SpaceController::class)->except('index');
    Route::get('spaces/{space}/pages/create', [PageController::class, 'create'])->name('pages.create');
    Route::post('spaces/{space}/pages', [PageController::class, 'store'])->name('pages.store');
    Route::get('pages/{page}', [PageController::class, 'show'])->name('pages.show');
    Route::get('pages/{page}/edit', [PageController::class, 'edit'])->name('pages.edit');
    Route::put('pages/{page}', [PageController::class, 'update'])->name('pages.update');
    Route::delete('pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
    Route::get('pages/{page}/history', [PageController::class, 'history'])->name('pages.history');
    Route::post('pages/{page}/history/{revision}/restore', [PageController::class, 'restore'])->name('pages.restore');

    Route::get('board', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');

    Route::get('monitors', [MonitorController::class, 'index'])->name('monitors.index');
    Route::post('monitors', [MonitorController::class, 'store'])->name('monitors.store');
    Route::post('monitors/check', [MonitorController::class, 'check'])->name('monitors.check');
    Route::post('monitors/{monitor}/check', [MonitorController::class, 'check'])->name('monitors.check-one');
    Route::put('monitors/{monitor}', [MonitorController::class, 'update'])->name('monitors.update');
    Route::delete('monitors/{monitor}', [MonitorController::class, 'destroy'])->name('monitors.destroy');

    Route::get('reminders', [ReminderController::class, 'index'])->name('reminders.index');
    Route::post('reminders', [ReminderController::class, 'store'])->name('reminders.store');
    Route::put('reminders/{reminder}', [ReminderController::class, 'update'])->name('reminders.update');
    Route::post('reminders/{reminder}/complete', [ReminderController::class, 'complete'])->name('reminders.complete');
    Route::post('reminders/{reminder}/reopen', [ReminderController::class, 'reopen'])->name('reminders.reopen');
    Route::post('reminders/{reminder}/snooze', [ReminderController::class, 'snooze'])->name('reminders.snooze');
    Route::delete('reminders/{reminder}', [ReminderController::class, 'destroy'])->name('reminders.destroy');
});
