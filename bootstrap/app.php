<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'password.changed' => \App\Http\Middleware\EnsurePasswordChanged::class,
        ]);
        
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login.show');
            }
            if ($request->is('portal') || $request->is('portal/*')) {
                return route('portal.login.show');
            }
            return route('portal.login.show');
        });

        $middleware->redirectUsersTo(function () {
            if (auth('admin')->check()) {
                return route('filament.admin.pages.dashboard');
            }
            if (auth('retailer')->check()) {
                return route('portal.dashboard');
            }
            return null;
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
