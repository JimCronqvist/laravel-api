<?php

namespace Cronqvist\Api\Models\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class BooleanNullableCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        return (bool) $value;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return $value ? 1 : 0;
    }
}
