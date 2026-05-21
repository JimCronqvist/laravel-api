<?php

namespace Cronqvist\Tenancy;

use Closure;
use Cronqvist\Tenancy\Eloquent\IsTenant;
use Cronqvist\Tenancy\Exceptions\TenantCouldNotBeIdentifiedException;
use Cronqvist\Tenancy\Identification\TenantResolver;

class Tenant
{
    /**
     * @var \Cronqvist\Tenancy\Eloquent\IsTenant
     */
    protected $tenant;

    /**
     * If the tenant resolver has been initialized
     *
     * @var bool
     */
    protected $initialized = false;

    /**
     * TenantResolver instance
     *
     * @var TenantResolver
     */
    protected $resolver;

    /**
     * @var Closure
     */
    public $isNoTenantAllowed;


    public function __construct(TenantResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Register a resolver
     *
     * @return self
     */
    public function registerResolver(callable $resolver)
    {
        $this->resolver->registerResolver($resolver);
        return $this;
    }

    /**
     * Register a resolver
     */
    public function setIsNoTenantAllowedCallback(Closure $closure)
    {
        $this->isNoTenantAllowed = $closure;
    }

    public function setTenant(?IsTenant $tenant)
    {
        $this->tenant = $tenant;
        $this->setInitialized(true);
        return $this;
    }

    public function getTenant(): ?IsTenant
    {
        return $this->tenant;
    }

    public function getTenantId(): ?int
    {
        return $this->getTenant() ? $this->getTenant()->getTenantKey() : null;
    }

    public function initialize(bool $refresh = false)
    {
        if(!$this->initialized || $refresh) {
            $tenant = $this->resolver->identify();

            $noTenantAllowed = !$tenant && is_callable($this->isNoTenantAllowed)
                ? (bool) call_user_func($this->isNoTenantAllowed, request())
                : false;

            if(!$tenant && !$noTenantAllowed) {
                throw new TenantCouldNotBeIdentifiedException('The tenant could not be identified.');
            }

            $this->setTenant($tenant);
        }
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function setInitialized(bool $initialized)
    {
        $this->initialized = $initialized;
        return $this;
    }
}
