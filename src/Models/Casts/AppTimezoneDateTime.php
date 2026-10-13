
<?php

namespace Cronqvist\Api\Models\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

class AppTimezoneDateTime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if($value === null) {
            return null;
        }
        return Date::parse($value, config('app.timezone'));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if($value === null) {
            return null;
        }

        $timezone = config('app.timezone');

        return Date::parse($value, $timezone)
            ->setTimezone($timezone)
            ->format($model->getDateFormat());
    }
}
