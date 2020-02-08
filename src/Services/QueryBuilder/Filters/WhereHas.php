<?php

namespace Cronqvist\Api\Services\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;

trait WhereHas
{
    protected $relation;

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
        $query->whereHas($this->relation, function(Builder $query) use($value, $property) {
            $parsed = $this->parseValue($value);
            $this->isOperatorAllowed($parsed['operator'], $this->allowedOperators, $property);
            $this->applyFilter($query, $property, $parsed['operator'], $parsed['value']);
        });
    }
}