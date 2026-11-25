<?php

use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\ScopeToCurrentUser;
use App\Http\Middleware\TrackNavVisit;
use App\Http\Middleware\VaultIdleLock;
use App\Http\Middleware\VaultNoRecording;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['two-factor' => RequireTwoFactor::class, 'vault.idle' => VaultIdleLock::class, 'vault.no-recording' => VaultNoRecording::class]);
        $middleware->web(append: [TrackNavVisit::class]);
        // Route-model binding must already see only the signed-in user's rows, so it has to come after sign-in but before binding.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ScopeToCurrentUser::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Vault form fields: a failed form is flashed back to the session, and these must not be stored there.
        $exceptions->dontFlash(['vault_username', 'vault_notes']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
