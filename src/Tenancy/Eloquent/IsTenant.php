<?php

namespace Cronqvist\Tenancy\Eloquent;

interface IsTenant
{
    /**
     * The attribute of the Model to use for the key
     */
    public function getTenantKeyName(): string;

    /**
     * The actual value of the key for the tenant Model
     *
     * @return string|int
     */
    public function getTenantKey();

    /**
     * Get the class name without the namespace of the instance
     *
     * @return string
     */
    public function getTenantClassName();

    /**
     * Get the tenant id column for relations to the tenant table in other tables.
     * For example when the Tenant is an instance of 'Customer', the expected column would be 'customer_id'
     *
     * @return string
     */
    public function getTenantIdColumn();
}
