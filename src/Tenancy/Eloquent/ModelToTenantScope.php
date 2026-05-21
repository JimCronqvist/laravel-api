<?php

namespace Cronqvist\Tenancy\Eloquent;

use Cronqvist\Tenancy\Facades\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ModelToTenantScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if(!Tenant::getTenantId()) {
            return;
        }

        $builder->whereHas($builder->getModel()->getRelationshipToModelBeforeTenant());
    }

    public function extend(Builder $builder)
    {
        $builder->macro('withoutTenancy', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
