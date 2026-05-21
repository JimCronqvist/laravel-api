<?php

namespace Cronqvist\Tenancy\Eloquent;

use Cronqvist\Tenancy\Facades\Tenant;

trait BelongsToTenant
{
    public function getTenantRelationName()
    {
        if(!Tenant::getTenantId()) {
            return null;
        }

        $relation = strtolower(Tenant::getTenant()->getTenantClassName());
        if(method_exists($this, $relation)) {
            return $relation;
        }
        return null;
    }

    public static function bootBelongsToTenant()
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if(Tenant::getTenantId()) {
                $tenantIdColumn = Tenant::getTenant()->getTenantIdColumn();
                if(!$model->getAttribute($tenantIdColumn)) {
                    $model->setAttribute($tenantIdColumn, Tenant::getTenantId());
                    if($model->getTenantRelationName() && !$model->relationLoaded($model->getTenantRelationName())) {
                        $model->setRelation($model->getTenantRelationName(), Tenant::getTenant());
                    }
                }
            }
        });
    }
}
