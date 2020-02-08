<?php

namespace Cronqvist\Api\Services\QueryBuilder\Filters;

class FilterWhereHasFull extends FilterFull
{
    use WhereHas;

    public function __construct($relation)
    {
        $this->relation = $relation;
    }
}
