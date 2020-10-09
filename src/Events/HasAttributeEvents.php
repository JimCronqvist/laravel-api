<?php

namespace Cronqvist\Api\Events;

use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Concerns\HasEvents;
use Illuminate\Support\Str;

trait HasAttributeEvents
{
    //use HasAttributes, HasEvents;


    /**
     * Laravel will automatically boot the trait using this method
     *
     * @return void
     */
    public static function bootHasAttributeEvents()
    {
        // Event fired after a model has been successfully saved (inserted or updated) by Laravel
        static::saved(function($model) {
            $model->fireAttributeEvents();
            $model->callAttributeSavedMethods();
        });
    }

    /**
     * Get all the attributes that will trigger an event
     *
     * @return array
     */
    protected function getAttributeEvents()
    {
        $events = [];
        foreach($this->dispatchesEvents as $event => $class) {
            if(strpos($event, ':') !== false) {
                $events[$event] = $class;
            }
        }
        return $events;
    }

    /**
     * Get all attributes that have a saved method
     *
     * @return array
     */
    protected function getAttributeSavedMethods()
    {
        $attributes = [];
        foreach($this->getDirty() as $attribute => $value) {
            if($this->hasAttributeSavedMethod($attribute)) {
                $attributes[] = $attribute;
            }
        }
        return $attributes;
    }

    /**
     * Determine if a saved method exists for an attribute
     *
     * @param string $attribute
     * @return bool
     */
    public function hasAttributeSavedMethod($attribute)
    {
        return method_exists($this, 'saved'.Str::studly($attribute).'Attribute');
    }

    /**
     * Call the saved method for an attribute
     *
     * @param string $attribute
     * @return mixed
     */
    protected function callAttributeSavedMethod($attribute)
    {
        return $this->{'saved'.Str::studly($attribute).'Attribute'}();
    }

    /**
     * Fire off events on all attributes that are matched and changed
     *
     * @return void
     */
    protected function fireAttributeEvents()
    {
        foreach($this->getAttributeEvents() as $attributeValue => $classOrMethod) {
            [$attribute, $v] = explode(':', $attributeValue);

            // Skip if the attribute does not exist or if it has not been changed
            if(!array_key_exists($attribute, $this->attributes)) continue;
            if(!$this->isDirty($attribute)) continue;

            // Get the value of the attribute
            $value = $this->getAttributeValue($attribute);

            // Decide if we are to fire the event based on if the value matches the specified attribute event
            if($v === '*' // Any - always call no matter what the value is
                || $v === 'true' && $value === true // bool(true)
                || $v === 'false' && $value === false // bool(false)
                || is_numeric($v) && strpos($v, '.') !== false && $value === (float) $v // float
                || is_numeric($v) && $value === (int) $v // int
                || $v === $value
            ) {
                $this->fireModelEvent($attribute, false);
            }
        }
    }

    /**
     * Call the saved method for every attribute that has been changed
     *
     * @return void
     */
    protected function callAttributeSavedMethods()
    {
        foreach($this->getAttributeSavedMethods() as $attribute) {
            $this->callAttributeSavedMethod($attribute);
        }
    }
}