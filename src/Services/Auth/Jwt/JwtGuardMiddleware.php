<?php

namespace Cronqvist\Api\Services\Auth\Jwt;

use Closure;
use Illuminate\Auth\RequestGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\PassportUserProvider;
use Laravel\Passport\TokenRepository;
use League\OAuth2\Server\ResourceServer;

class JwtGuardMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        Auth::resolved(function($auth) {
            $auth->extend('jwt', function ($app, $name, array $config) {
                return tap($this->makeGuard($config), function ($guard) {
                    app()->refresh('request', $guard, 'setRequest');
                });
            });
        });

        Config::set('auth.guards.api.driver', 'jwt');
        return $next($request);
    }

    /**
     * Make an instance of the token guard.
     *
     * @param  array  $config
     * @return \Illuminate\Auth\RequestGuard
     */
    protected function makeGuard(array $config)
    {
        return new RequestGuard(function($request) use($config) {
            return (new JwtTokenGuard(
                app()->make(ResourceServer::class),
                new PassportUserProvider(Auth::createUserProvider($config['provider']), $config['provider']),
                app()->make(TokenRepository::class),
                app()->make(ClientRepository::class),
                app()->make('encrypter')
            ))->user($request);
        }, request());
    }
}
