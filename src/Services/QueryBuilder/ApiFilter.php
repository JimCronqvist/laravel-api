<?php

namespace Cronqvist\Api\Services\QueryBuilder;

use Cronqvist\Api\Services\QueryBuilder\Filters\FilterEqual;
use Cronqvist\Api\Services\QueryBuilder\Filters\FilterFull;
use Cronqvist\Api\Services\QueryBuilder\Filters\FilterStandard;
use Spatie\QueryBuilder\AllowedFilter;

class ApiFilter
{
    /**
     * Filters: eq, neq, in, nin
     *
     * @see \Cronqvist\Api\Services\QueryBuilder\Filters\FilterEqual::$allowedOperators
     * @param string $column
     * @return \Spatie\QueryBuilder\AllowedFilter
     */
    public static function equal($column)
    {
        return AllowedFilter::custom($column, new FilterEqual());
    }

    /**
     * All filters except the ones involving a 'like'
     *
     * @see \Cronqvist\Api\Services\QueryBuilder\Filters\FilterStandard::$allowedOperators
     * @param string $column
     * @return \Spatie\QueryBuilder\AllowedFilter
     */
    public static function standard($column)
    {
        return AllowedFilter::custom($column, new FilterStandard());
    }

    /**
     * All filters
     *
     * @see \Cronqvist\Api\Services\QueryBuilder\Filters\FilterFull::$allowedOperators
     * @param string $column
     * @return \Spatie\QueryBuilder\AllowedFilter
     */
    public static function full($column)
    {
        return AllowedFilter::custom($column, new FilterFull());
    }

    /**
     * Scope filter
     *
     * @param string $scope
     * @return \Spatie\QueryBuilder\AllowedFilter
     */
    public static function scope($scope)
    {
        return AllowedFilter::scope($scope);
    }
}