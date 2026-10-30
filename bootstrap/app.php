<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // boka-demo: endpointen bara loggar ett bolagsnamn och är rate-limitad;
        // sidan kan vara cachad utan CSRF-token. webhooks/intercom: anropet
        // kommer från Intercom och verifieras med HMAC-signatur i stället.
        $middleware->web(append: [\App\Http\Middleware\NoIndexPaths::class]);

        $middleware->preventRequestForgery(except: ['boka-demo/log', 'boka-demo/lookup', 'webhooks/intercom']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // abort(404) i våra egna routes (t.ex. /byra/..., /support/...) renderar
        // felsidan som en vanlig vy, utan layout, och sidan blir utan stilmall
        // och skript. Statamics egen 404 lägger den i layouten som sidorna i övrigt.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($e instanceof \Statamic\Exceptions\NotFoundHttpException
                || $request->is('cp/*', 'api/*')
                || $request->expectsJson()) {
                return null;
            }

            return (new \Statamic\Exceptions\NotFoundHttpException($e->getMessage(), $e))->render($request);
        });
    })->create();
