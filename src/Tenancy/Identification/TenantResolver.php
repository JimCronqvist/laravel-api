<?php

namespace Cronqvist\Tenancy\Identification;

use Cronqvist\Tenancy\Eloquent\IsTenant;

class TenantResolver
{
    /**
     * Callable resolvers to find Tenant models
     *
     * @var array
     */
    protected $resolvers = [];

    /**
     * Register a resolver
     *
     * @return self
     */
    public function registerResolver(callable $resolver)
    {
        $this->resolvers[] = $resolver;
        return $this;
    }

    /**
     * Identify the tenant model from the resolvers
     */
    public function identify(): ?IsTenant
    {
        foreach($this->resolvers as $resolver) {
            if(is_callable($resolver)) {
                $tenant = $resolver(request());
                if($tenant instanceof IsTenant) {
                    return $tenant;
                }
            }
        }
        return null;
    }
}
