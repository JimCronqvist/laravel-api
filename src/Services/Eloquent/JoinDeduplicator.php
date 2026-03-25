<?php

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;

class JoinDeduplicator
{
    public function deduplicate(Builder $builder): Builder
    {
        $query = $builder->getQuery();

        if (empty($query->joins)) {
            return $builder;
        }

        $seen = [];
        $result = [];

        foreach ($query->joins as $join) {
            /** @var JoinClause $join */

            [$table, $alias] = $this->parseTableAndAlias($join->table);

            $conditions = collect($join->wheres ?? [])
                ->map(fn ($where) => $this->normalizeWhere($where))
                ->sortBy(fn ($c) => implode('|', $c))
                ->values()
                ->all();

            $signature = $this->makeSignature(
                $table,
                $alias,
                $join->type,
                $conditions
            );

            if (!isset($seen[$signature])) {
                $seen[$signature] = true;
                $result[] = $join;
            }
        }

        $query->joins = $result;

        return $builder;
    }

    protected function makeSignature($table, $alias, $type, array $conditions): string
    {
        return serialize([
            'table' => $table,
            'alias' => $alias,
            'type' => $type,
            'conditions' => $conditions,
        ]);
    }

    protected function normalizeWhere(array $where): array
    {
        return [
            'type' => $where['type'] ?? 'basic',
            'first' => $this->normalizeValue($where['first'] ?? null),
            'operator' => $where['operator'] ?? null,
            'second' => $this->normalizeValue($where['second'] ?? null),
            'boolean' => $where['boolean'] ?? 'and',
        ];
    }

    protected function normalizeValue($value)
    {
        if ($value instanceof Expression) {
            return $value->getValue();
        }

        return is_string($value) ? trim($value) : $value;
    }

    protected function parseTableAndAlias($table): array
    {
        if ($table instanceof Expression) {
            $table = $table->getValue();
        }

        $table = trim($table);

        if (Str::contains(strtolower($table), ' as ')) {
            [$base, $alias] = preg_split('/\s+as\s+/i', $table);
            return [trim($base), trim($alias)];
        }

        $parts = preg_split('/\s+/', $table);

        if (count($parts) === 2) {
            return [trim($parts[0]), trim($parts[1])];
        }

        return [$table, null];
    }
}
