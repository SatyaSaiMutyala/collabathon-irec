<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the whole admin panel. Being authenticated is not enough — a broker or
 * developer holding a valid session must not reach /admin.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin() || ! $user->isActive()) {
            abort(403, 'Admin access only.');
        }

        return $next($request);
    }
}
