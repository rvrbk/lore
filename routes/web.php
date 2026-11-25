<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarSourceController;
use App\Http\Controllers\ConfirmPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HeatmapController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\MarkdownController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\MonitorSubscriptionController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PointyBookingController;
use App\Http\Controllers\PointyController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SpaceController;
use App\Http\Controllers\StatusPageController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TelegramSettingsController;
use App\Http\Controllers\TwoFactorChallengeController;
use App\Http\Controllers\TwoFactorSettingsController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\VaultController;
use App\Http\Controllers\VaultExportController;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\WorldClockController;
use App\Http\Middleware\ScopeToCurrentUser;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'form'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});

Route::get('pointy/booking/{token}', [PointyBookingController::class, 'booking'])->name('pointy.booking');
Route::post('pointy/booking/{token}/cancel', [PointyBookingController::class, 'cancel'])->middleware('throttle:10,1')->name('pointy.cancel');
Route::get('pointy/{slug}', [PointyBookingController::class, 'show'])->name('pointy.show');
Route::post('pointy/{slug}', [PointyBookingController::class, 'book'])->middleware('throttle:10,1')->name('pointy.book');

Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'verify'])->middleware('throttle:5,1,two-factor-challenge')->name('two-factor.verify');

Route::get('status/{token}', StatusPageController::class)->name('status.show');

Route::middleware(['auth', ScopeToCurrentUser::class])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('weather', WeatherController::class)->name('weather');
    Route::get('confirm-password', [ConfirmPasswordController::class, 'show'])->name('password.confirm');
    Route::post('confirm-password', [ConfirmPasswordController::class, 'confirm'])->middleware('throttle:6,1,password-confirm');
    Route::get('settings/two-factor', [TwoFactorSettingsController::class, 'show'])->name('settings.two-factor');
    Route::middleware('password.confirm')->group(function () {
        Route::post('settings/two-factor', [TwoFactorSettingsController::class, 'start'])->name('settings.two-factor.start');
        Route::post('settings/two-factor/recovery-codes', [TwoFactorSettingsController::class, 'regenerateRecoveryCodes'])->name('settings.two-factor.recovery-codes');
        Route::delete('settings/two-factor', [TwoFactorSettingsController::class, 'disable'])->name('settings.two-factor.disable');
    });
    Route::post('settings/two-factor/confirm', [TwoFactorSettingsController::class, 'confirm'])->middleware('throttle:5,1,two-factor-setup')->name('settings.two-factor.confirm');

    // The password vault: two-factor, a recent password confirmation (which lapses after idle time) and rate limits on every route.
    Route::middleware(['vault.no-recording', 'two-factor', 'vault.idle', 'password.confirm', 'throttle:60,1,vault'])->prefix('vault')->name('vault.')->group(function () {
        Route::get('export', VaultExportController::class)->middleware('throttle:3,60,vault-export')->name('export');
        Route::get('/', [VaultController::class, 'index'])->name('index');
        Route::post('/', [VaultController::class, 'store'])->name('store');
        Route::get('{item}/edit', [VaultController::class, 'edit'])->name('edit');
        Route::put('{item}', [VaultController::class, 'update'])->name('update');
        Route::delete('{item}', [VaultController::class, 'destroy'])->name('destroy');
        Route::post('{item}/reveal', [VaultController::class, 'reveal'])->middleware('throttle:30,1,vault-reveal')->name('reveal');
    });
    Route::get('settings', [SettingsController::class, 'show'])->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('settings/telegram', [TelegramSettingsController::class, 'show'])->name('settings.telegram');
    Route::post('settings/telegram/check', [TelegramSettingsController::class, 'check'])->name('settings.telegram.check');
    Route::put('settings/telegram/digest', [TelegramSettingsController::class, 'digest'])->name('settings.telegram.digest');
    Route::post('settings/telegram/test', [TelegramSettingsController::class, 'test'])->name('settings.telegram.test');
    Route::delete('settings/telegram', [TelegramSettingsController::class, 'disconnect'])->name('settings.telegram.disconnect');
    Route::post('calendar-sources', [CalendarSourceController::class, 'store'])->name('calendar-sources.store');
    Route::delete('calendar-sources/{calendarSource}', [CalendarSourceController::class, 'destroy'])->name('calendar-sources.destroy');

    Route::post('world-clocks', [WorldClockController::class, 'store'])->name('world-clocks.store');
    Route::post('world-clocks/reorder', [WorldClockController::class, 'reorder'])->name('world-clocks.reorder');
    Route::patch('world-clocks/{worldClock}/style', [WorldClockController::class, 'toggleStyle'])->name('world-clocks.style');
    Route::patch('world-clocks/{worldClock}/header', [WorldClockController::class, 'toggleHeader'])->name('world-clocks.header');
    Route::delete('world-clocks/{worldClock}', [WorldClockController::class, 'destroy'])->name('world-clocks.destroy');
    Route::get('scheduling', [PointyController::class, 'index'])->name('pointy.index');
    Route::post('scheduling/types', [PointyController::class, 'store'])->name('pointy.types.store');
    Route::put('scheduling/types/{bookingType}', [PointyController::class, 'update'])->name('pointy.types.update');
    Route::delete('scheduling/types/{bookingType}', [PointyController::class, 'destroy'])->name('pointy.types.destroy');
    Route::post('scheduling/types/{bookingType}/exceptions', [PointyController::class, 'storeException'])->name('pointy.exceptions.store');
    Route::delete('scheduling/exceptions/{exception}', [PointyController::class, 'destroyException'])->name('pointy.exceptions.destroy');
    Route::post('scheduling/sites', [PointyController::class, 'storeSite'])->name('pointy.sites.store');
    Route::put('scheduling/sites/{site}', [PointyController::class, 'updateSite'])->name('pointy.sites.update');
    Route::delete('scheduling/sites/{site}', [PointyController::class, 'destroySite'])->name('pointy.sites.destroy');
    Route::post('scheduling/requests', [PointyController::class, 'sendRequest'])->name('pointy.requests.send');
    Route::post('scheduling/bookings/{booking}/cancel', [PointyController::class, 'cancel'])->name('pointy.bookings.cancel');
    Route::get('/search', SearchController::class)->name('search');
    Route::get('/search/quick', [SearchController::class, 'quick'])->name('search.quick');
    Route::get('palette', [SearchController::class, 'palette'])->name('palette');
    Route::post('markdown/preview', MarkdownController::class)->name('markdown.preview');
    Route::post('uploads', [UploadController::class, 'store'])->name('uploads.store');
    Route::get('uploads/{filename}', [UploadController::class, 'show'])->name('uploads.show');
    Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    Route::get('tags/suggestions', [TagController::class, 'suggestions'])->name('tags.suggestions');
    Route::get('tags/{tag}', [TagController::class, 'show'])->name('tags.show');
    Route::get('export', [ExportController::class, 'all'])->name('export.all');
    Route::get('spaces/{space}/export', [ExportController::class, 'space'])->name('export.space');

    Route::resource('spaces', SpaceController::class)->except('index');
    Route::get('spaces/{space}/pages/create', [PageController::class, 'create'])->name('pages.create');
    Route::post('spaces/{space}/pages', [PageController::class, 'store'])->name('pages.store');
    Route::get('pages/{page}', [PageController::class, 'show'])->name('pages.show');
    Route::get('pages/{page}/edit', [PageController::class, 'edit'])->name('pages.edit');
    Route::put('pages/{page}', [PageController::class, 'update'])->name('pages.update');
    Route::delete('pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
    Route::post('pages/{page}/pin', [PageController::class, 'pin'])->name('pages.pin');
    Route::get('pages/{page}/history', [PageController::class, 'history'])->name('pages.history');
    Route::post('pages/{page}/history/{revision}/restore', [PageController::class, 'restore'])->name('pages.restore');

    Route::get('board', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('board/archive', [TaskController::class, 'archive'])->name('tasks.archive');
    Route::post('tasks/{task}/restore', [TaskController::class, 'restore'])->name('tasks.restore');
    Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');

    Route::post('monitors/{monitor}/subscription', [MonitorSubscriptionController::class, 'store'])->name('monitors.subscribe');
    Route::delete('monitors/{monitor}/subscription', [MonitorSubscriptionController::class, 'destroy'])->name('monitors.unsubscribe');
    Route::get('monitors', [MonitorController::class, 'index'])->name('monitors.index');
    Route::get('monitors/{monitor}', [MonitorController::class, 'show'])->name('monitors.show');
    Route::post('monitors/check', [MonitorController::class, 'check'])->name('monitors.check');
    Route::post('monitors/{monitor}/check', [MonitorController::class, 'check'])->name('monitors.check-one');
    Route::put('monitors/{monitor}', [MonitorController::class, 'update'])->name('monitors.update');
    Route::delete('monitors/{monitor}', [MonitorController::class, 'destroy'])->name('monitors.destroy');

    Route::get('incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::put('incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
    Route::post('incidents/{incident}/post-mortem', [IncidentController::class, 'postMortem'])->name('incidents.post-mortem');

    Route::get('deploy', [DeployController::class, 'index'])->name('deploy.index');
    Route::get('deploy/active', [DeployController::class, 'active'])->name('deploy.active');
    Route::get('deploy/state', [DeployController::class, 'state'])->name('deploy.state');
    Route::post('deploy', [DeployController::class, 'store'])->name('deploy.store');

    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('news', NewsController::class)->name('news.headlines');
    Route::get('analytics/live', [AnalyticsController::class, 'live'])->name('analytics.live');
    Route::post('analytics', [AnalyticsController::class, 'store'])->name('analytics.store');
    Route::delete('analytics/{site}', [AnalyticsController::class, 'destroy'])->name('analytics.destroy');

    Route::redirect('heatmaps', '/analytics');
    Route::get('analytics/heatmaps/{monitor}', [HeatmapController::class, 'show'])->name('analytics.heatmaps.show');

    Route::get('reminders', [ReminderController::class, 'index'])->name('reminders.index');
    Route::post('reminders', [ReminderController::class, 'store'])->name('reminders.store');
    Route::put('reminders/{reminder}', [ReminderController::class, 'update'])->name('reminders.update');
    Route::post('reminders/{reminder}/complete', [ReminderController::class, 'complete'])->name('reminders.complete');
    Route::post('reminders/{reminder}/reopen', [ReminderController::class, 'reopen'])->name('reminders.reopen');
    Route::post('reminders/{reminder}/snooze', [ReminderController::class, 'snooze'])->name('reminders.snooze');
    Route::delete('reminders/{reminder}', [ReminderController::class, 'destroy'])->name('reminders.destroy');
});
