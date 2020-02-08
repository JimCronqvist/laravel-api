<?php

namespace Cronqvist\Api\Services\QueryBuilder;

use Cronqvist\Api\Services\QueryBuilder\Filters\FilterEqual;
use Cronqvist\Api\Services\QueryBuilder\Filters\FilterFull;
use Cronqvist\Api\Services\QueryBuilder\Filters\FilterStandard;
use Cronqvist\Api\Services\QueryBuilder\Filters\FilterWhereHasEqual;
use Cronqvist\Api\Services\QueryBuilder\Filters\FilterWhereHasStandard;
use Cronqvist\Api\Services\QueryBuilder\Filters\FilterWhereHasFull;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\Filters\Filter;

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

    /**
     * Custom filter
     *
     * @param string $column
     * @param \Spatie\QueryBuilder\Filters\Filter $filterClass
     * @return \Spatie\QueryBuilder\AllowedFilter
     */
    public static function custom($column, Filter $filterClass)
    {
        return AllowedFilter::custom($column, $filterClass);
    }

    /**
     * WhereHasEqual filter
     *
     * @param string $name
     * @param string $relation
     * @param string $column
     * @return \Spatie\QueryBuilder\AllowedFilter
     * @see equal
     */
    public static function whereHasEqual($name, $relation, $column = null)
    {
        return AllowedFilter::custom($name, new FilterWhereHasEqual($relation), $column);
    }

    /**
     * WhereHasStandard filter
     *
     * @param string $name
     * @param string $relation
     * @param string $column
     * @return \Spatie\QueryBuilder\AllowedFilter
     * @see equal
     */
    public static function whereHasStandard($name, $relation, $column = null)
    {
        return AllowedFilter::custom($name, new FilterWhereHasStandard($relation), $column);
    }

    /**
     * WhereHasFull filter
     *
     * @param string $name
     * @param string $relation
     * @param string $column
     * @return \Spatie\QueryBuilder\AllowedFilter
     * @see equal
     */
    public static function whereHasFull($name, $relation, $column = null)
    {
        return AllowedFilter::custom($name, new FilterWhereHasFull($relation), $column);
    }
}