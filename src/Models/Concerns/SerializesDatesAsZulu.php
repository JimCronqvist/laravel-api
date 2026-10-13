<?php

namespace Cronqvist\Api\Models\Concerns;

use DateTimeInterface;
use Illuminate\Support\Carbon;

trait SerializesDatesAsZulu
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)
            ->utc()
            ->toIso8601ZuluString();
    }
}
