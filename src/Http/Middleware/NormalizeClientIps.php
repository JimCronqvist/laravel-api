<?php

namespace Cronqvist\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NormalizeClientIps
{
    public static array $replaceHeaders = [
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR',
    ];

    public function handle(Request $request, Closure $next)
    {
        // Normalize IPv4-mapped IPv6 addresses in headers like X-Forwarded-For and REMOTE_ADDR
        foreach(static::$replaceHeaders as $header) {
            $value = $request->server($header, '');
            if(str_contains($value, '::ffff:')) {
                $normalizedIps = collect(explode(',', $value))
                    ->map(fn($ip) => str_starts_with($ip = trim($ip), '::ffff:') ? substr($ip, 7) : $ip)
                    ->implode(', ');

                $request->server->set($header, $normalizedIps);
                if(str_starts_with($header, 'HTTP_')) {
                    $request->headers->set(str_replace('HTTP_', '', $header), $normalizedIps);
                }
            }
        }

        return $next($request);
    }
}