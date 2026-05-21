<?php

namespace Cronqvist\Tenancy\Middleware;

use Closure;
use Cronqvist\Tenancy\Exceptions\TenantCouldNotBeIdentifiedException;
use Cronqvist\Tenancy\Facades\Tenant;
use Illuminate\Http\Request;

class TenantMiddleware
{
    /**
     * Middleware handle
     *
     * @return mixed
     * @throws \Cronqvist\Tenancy\Exceptions\TenantCouldNotBeIdentifiedException
     */
    public function handle(Request $request, Closure $next)
    {
        if($request->method() !== 'OPTIONS') {
            try {
                Tenant::initialize();
            } catch (TenantCouldNotBeIdentifiedException $exception) {
                throw $exception;
            }
        }
        return $next($request);
    }
}
