<?php

namespace Cronqvist\Api\Services\QueryBuilder\Filters;

class FilterWhereHasEqual extends FilterEqual
{
    use WhereHas;

    public function __construct($relation)
    {
        $this->relation = $relation;
    }
}
