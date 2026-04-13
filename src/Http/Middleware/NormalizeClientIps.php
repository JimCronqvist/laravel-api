<?php

namespace Cronqvist\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NormalizeClientIps
{
    public function handle(Request $request, Closure $next)
    {
        // Normalize IPv4-mapped IPv6 addresses in X-Forwarded-For
        if(str_contains($request->server('HTTP_X_FORWARDED_FOR', ''), '::ffff:')) {
            $normalizedIps = collect(explode(',', $request->server('HTTP_X_FORWARDED_FOR')))
                ->map(fn($ip) => preg_replace('/^::ffff:/', '', trim($ip)))
                ->implode(', ');

            $request->server->set('HTTP_X_FORWARDED_FOR', $normalizedIps);
        }

        // Normalize IPv4-mapped IPv6 addresses in REMOTE_ADDR
        if(str_contains($request->server('REMOTE_ADDR', ''), '::ffff:')) {
            $request->server->set('REMOTE_ADDR', preg_replace('/^::ffff:/', '', $request->server('REMOTE_ADDR')));
        }

        return $next($request);
    }
}