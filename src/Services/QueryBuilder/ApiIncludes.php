<?php

namespace Cronqvist\Api\Services\QueryBuilder;

use Cronqvist\Api\Services\QueryBuilder\Includes\IncludedDummy;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\AllowedInclude;

class ApiIncludes extends AllowedInclude
{

    public static function dummy(string $name, $internalName = null): Collection
    {
        return collect([
            new static($name, new IncludedDummy(), $internalName),
        ]);
    }
}