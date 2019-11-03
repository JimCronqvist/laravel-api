<?php

namespace Cronqvist\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiGuardMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        Auth::shouldUse('api'); // Set the 'api' guard as "default" when this middleware is used

        return $next($request);
    }
}
