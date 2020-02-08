<?php

namespace Cronqvist\Api\Services\QueryBuilder\Filters;

class FilterWhereHasStandard extends FilterStandard
{
    use WhereHas;

    public function __construct($relation)
    {
        $this->relation = $relation;
    }
}
