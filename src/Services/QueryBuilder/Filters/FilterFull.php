<?php

namespace Cronqvist\Api\Services\QueryBuilder\Filters;

class FilterFull extends AbstractFilterRhs
{
    /**
     * Allowed Operators for this filter set
     *
     * @var array
     */
    protected $allowedOperators = [
        'eq', 'neq',
        'gt', 'gte', 'lt', 'lte',
        'in', 'nin',
        'btw', 'nbtw',
        'like', 'nlike', 'starts', 'ends', 'nstarts', 'nends'
    ];
}
