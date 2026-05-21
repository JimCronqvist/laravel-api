<?php

namespace Cronqvist\Tenancy\Facades;

use Cronqvist\Tenancy\Tenant as Tenancy;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Cronqvist\Tenancy\Eloquent\IsTenant getTenant()
 * @method static int getTenantId()
 * @method static bool isInitialized()
 * @method static void registerResolver(callable $resolver)
 * @see \Cronqvist\Tenancy\Tenant
 */
class Tenant extends Facade
{
    public static function getFacadeAccessor()
    {
        return Tenancy::class;
    }
}
