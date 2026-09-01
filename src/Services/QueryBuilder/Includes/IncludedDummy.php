<?php

namespace Cronqvist\Api\Services\QueryBuilder\Includes;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Includes\IncludeInterface;

class IncludedDummy implements IncludeInterface
{
    public function __invoke(Builder $query, string $include)
    {

    }
}
