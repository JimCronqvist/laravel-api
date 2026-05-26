<?php

namespace Cronqvist\Tenancy\Eloquent;

use Cronqvist\Tenancy\Facades\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if(!Tenant::getTenantId()) {
            return;
        }

        $tenantIdColumn = $this->getTenantIdColumn($model);
        $builder->where($model->qualifyColumn($tenantIdColumn), '=', Tenant::getTenantId());
    }

    protected function getTenantIdColumn(Model $model)
    {
        // Default key
        $tenantIdColumn = Tenant::getTenant()->getTenantIdColumn();

        // When the Tenant scope is applied on its own model, check against auto increment id
        if(get_class($model) === get_class(Tenant::getTenant())) {
            $tenantIdColumn = $model->getKeyName();
        }

        // If overridden per model, if set to null, it is ignored.
        if(isset($model->tenantIdColumn)) {
            $tenantIdColumn = $model->tenantIdColumn;
        }

        return $tenantIdColumn;
    }

    public function extend(Builder $builder)
    {
        $builder->macro('withoutTenancy', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
