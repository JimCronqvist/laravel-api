<?php

namespace Cronqvist\Api\Http\Middleware;

use Closure;
use Cronqvist\Api\Services\Auth\AuthService;
use Illuminate\Http\Request;

class AccessTokenCookieMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if(!$request->bearerToken()
            && AuthService::$useAccessTokenCookie
            && ($token = $request->cookie(AuthService::$accessToken))
        ) {
            $request->headers->set('Authorization', "Bearer $token");
            $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token"; // We also use the global variable within this library
        }
        return $next($request);
    }
}
