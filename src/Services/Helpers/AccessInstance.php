<?php

namespace Cronqvist\Api\Services\Helpers;

use \Closure;

class AccessInstance
{
    public static function call($instance, Closure $function)
    {
        return $function->call($instance);
    }

    public static function getProperty($instance, $property)
    {
        return (function() use($property) {
            return $this->{$property};
        })->call($instance);
    }
}