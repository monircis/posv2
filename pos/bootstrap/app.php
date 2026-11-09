<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The user and permissions come from the auth service's token, not from this database.
        $middleware->alias([
            'auth.token' => \App\Http\Middleware\VerifyToken::class,
            'permission' => \App\Http\Middleware\Permission::class,
        ]);

        // Check the token and the permission before a record in the URL is looked up,
        // so a user without the permission gets 403 and cannot tell which records exist.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\VerifyToken::class,
        );
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\Permission::class,
        );

        // The frontend sends '' for empty text fields and expects '' back.
        $middleware->convertEmptyStringsToNull(except: [
            fn (Request $request) => $request->is('api/*'),
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API errors are always JSON.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
    })->create();
