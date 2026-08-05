<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * Where the `guest` middleware sends someone who is already signed in.
         *
         * Without this it falls back to Laravel's default of '/', which this app
         * redirects straight back to /login — so an authenticated admin hitting the
         * site root bounced between the two until the browser gave up with
         * ERR_TOO_MANY_REDIRECTS. The panel is the only place a signed-in user belongs.
         */
        $middleware->redirectUsersTo('/admin/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        /**
         * An over-sized upload is a routine mistake on the project intake form, not a crash.
         * PHP discards the entire body once Content-Length passes `post_max_size`, so there
         * are no fields left to repopulate — the least-bad answer is to send the admin back
         * with an explanation instead of a stack trace. The form's own client-side check
         * normally catches this first; this is the backstop for anything that slips past.
         */
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->is('api/*')) {
                return null;
            }

            $limit = (string) ini_get('post_max_size');

            return back()->withErrors([
                'attachments' => "Those attachments exceed the {$limit} the server accepts in one "
                    . 'submit. Add the project with fewer files, then attach the rest by editing it.',
            ]);
        });
    })->create();
