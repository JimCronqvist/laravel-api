<?php

namespace Cronqvist\Api\Services\QueryBuilder\Filters;

use Cronqvist\Api\Exception\ApiException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait RhsColonSyntax
{
    protected $operators = [
        'eq'      => 'equals',
        'neq'     => 'not equals',
        'gt'      => 'greater than',
        'gte'     => 'greater than or equal',
        'lt'      => 'less than',
        'lte'     => 'less than or equal',
        'in'      => 'any of list',
        'nin'     => 'not any of list',
        'btw'     => 'between two values',
        'nbtw'    => 'not between two values',
        'like'    => 'contains the specified string',
        'nlike'   => 'not contains the specified string',
        'starts'  => 'starts with',
        'ends'    => 'ends with',
        'nstarts' => 'not starts with',
        'nends'   => 'not ends with',
    ];

    /**
     * RHS Colon Syntax operators mapped to the Builder operators
     *
     * @see \Illuminate\Database\Query\Builder::$operators
     * @var array
     */
    protected $operatorsMap = [
        'eq'      => '=',
        'neq'     => '!=',
        'gt'      => '>',
        'gte'     => '>=',
        'lt'      => '<',
        'lte'     => '<=',
        'in'      => '=',
        'nin'     => '!=',
        'btw'     => null, // No mapping for 'BETWEEN'
        'nbtw'    => null, // No mapping for 'NOT BETWEEN'
        'like'    => 'like',
        'nlike'   => 'not like',
        'starts'  => 'like',
        'nstarts' => 'not like',
        'ends'    => 'like',
        'nends'   => 'not like',
    ];

    /**
     * Parse the value, return the operator and value to us
     *
     * @param string|array $value
     * @return array
     */
    protected function parseValue($value)
    {
        if(is_array($value)) {
            $first = array_shift($value);
            $split = explode(':', $first, 2);
            $split[1] = implode(',', array_merge([$split[1]], $value));
        } else {
            $split = explode(':', $value, 2);
        }
        if(count($split) == 2 && in_array($split[0], array_keys($this->operators))) {
            $operator = $split[0];
            $value = $split[1];
        }
        return [
            'operator' => $operator ?? 'eq',
            'value' => $value,
        ];
    }

    /**
     * Ensure that the operator is allowed, throw an exception otherwise
     *
     * @param string $operator
     * @param array $allowedOperators
     * @param string $column
     * @return bool
     * @throws \Cronqvist\Api\Exception\ApiException
     */
    protected function isOperatorAllowed(string $operator, array $allowedOperators, string $column)
    {
        if(!in_array($operator, $allowedOperators)) {
            throw new ApiException(sprintf(
                "The operator '%s' is not allowed in the filter of property '%s'. Allowed operators are: %s",
                $operator,
                $column,
                '`' . implode('`, `', $allowedOperators) . '`'
            ));
        }
        return true;
    }

    /**
     * Apply a filter
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $column
     * @param string $operator
     * @param string $value
     * @throws \Cronqvist\Api\Exception\ApiException
     */
    protected function applyFilter(Builder $query, string $column, string $operator, string $value)
    {
        // Do some final preparations of the value
        if(in_array($operator, ['in', 'nin'])) {
            $value = explode(',', $value);
        }
        else if(Str::contains($this->operatorsMap[$operator], 'like')) {
            $value = sprintf('%s%s%s',
                !Str::contains($operator, 'starts') ? '%' : '',
                str_replace(['_', '%'], ['\_', '\%'], $value),
                !Str::contains($operator, 'ends') ? '%' : ''
            );
        }
        else if(in_array($operator, ['btw', 'nbtw'])) {
            $value = explode(',', $value, 2);
            if(count($value) < 2) {
                throw new ApiException('Between could not be parsed, two values has not been provided.');
            }
        }

        // Apply the filter to the Builder
        if(in_array($operator, ['btw', 'nbtw'])) {
            $query->whereBetween($column, $value, 'and', $operator == 'nbtw');
        } else if(in_array($operator, ['in', 'nin'])) {
            $query->whereIn($column, $value, 'and', $operator == 'nin');
        } else {
            $query->where($column, $this->operatorsMap[$operator], $value);
        }
    }
}