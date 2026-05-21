<?php

namespace Cronqvist\Tenancy\Eloquent;

trait BelongsToTenantIndirectly
{
    abstract public function getRelationshipToModelBeforeTenant(): string;

    public static function bootBelongsToTenantIndirectly()
    {
        static::addGlobalScope(new ModelToTenantScope());
    }
}
