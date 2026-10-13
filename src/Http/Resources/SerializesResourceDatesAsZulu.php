<?php

namespace Cronqvist\Api\Http\Resources;

use DateTimeInterface;
use Illuminate\Support\Carbon;

trait SerializesResourceDatesAsZulu
{
    protected function serializeDates(mixed $value): mixed
    {
        if($value instanceof DateTimeInterface) {
            return Carbon::instance($value)
                ->utc()
                ->toIso8601ZuluString();
        }

        if(is_array($value)) {
            return array_map($this->serializeDates(...), $value);
        }

        return $value;
    }
}
