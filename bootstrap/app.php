<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(prepend: [
            \App\Http\Middleware\CachePublicPagesAtEdge::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'tasks/import-rss/*',
            'tasks/import-almanar-urgent/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Throwable $exception): bool|null {
            if (! app()->runningInConsole() && request()->is('admin/*')) {
                error_log(sprintf(
                    '[shaghilla-admin-error] %s: %s at %s:%d',
                    $exception::class,
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine(),
                ));

                return false;
            }

            return null;
        });

        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->isMethod('post') && $request->routeIs('membership.store')) {
                return redirect()
                    ->route('membership')
                    ->withErrors(['session' => __('ui.messages.session_expired')]);
            }

            return null;
        });
    })->create();
