<?php

namespace Cronqvist\Api\Services\QueryBuilder;

use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder as BaseQueryBuilder;

class QueryBuilder extends BaseQueryBuilder
{
    public function allowedSorts($sorts) : BaseQueryBuilder
    {
        $sorts = is_array($sorts) ? $sorts : func_get_args();
        $this->allowedSorts = collect($sorts)->map(function($sort) {
            if($sort instanceof AllowedSort) {
                return $sort;
            }
            return AllowedSort::field(ltrim($sort, '-'));
        });
        $this->ensureAllSortsExist();
        $this->addRequestedSortsToQuery();
        return $this;
    }
}
