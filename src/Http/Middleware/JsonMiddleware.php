<?php

namespace Cronqvist\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class JsonMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if(!$request->query('debug')) {
            $request->headers->set('Accept', 'application/json');
        }
        return $next($request);
    }
}
