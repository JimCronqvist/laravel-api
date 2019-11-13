<?php

namespace Cronqvist\Api\Services\QueryBuilder\Filters;

use Spatie\QueryBuilder\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

abstract class AbstractFilterRhs implements Filter
{
    use RhsColonSyntax;

    /**
     * Allowed Operators for this filter set
     *
     * @var array
     */
    protected $allowedOperators = [];


    /**
     * Invoke the filter
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $value
     * @param string $property
     * @throws \Cronqvist\Api\Exception\ApiException
     */
    public function __invoke(Builder $query, $value, string $property)
    {
        $parsed = $this->parseValue($value);
        $this->isOperatorAllowed($parsed['operator'], $this->allowedOperators, $property);
        $this->applyFilter($query, $property, $parsed['operator'], $parsed['value']);
    }
}
