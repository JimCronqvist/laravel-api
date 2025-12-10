<?php

namespace Cronqvist\Api\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait GetResource
{
    protected function getResourceValue()
    {
        $class = class_basename(static::class);
        $param = Str::snake(Str::beforeLast($class, 'Request'));
        return $this->route()->parameter($param);
    }

    protected function getResourceId(): ?int
    {
        $value = $this->getResourceValue();
        if($value instanceof Model) return $value->getKey();
        if(is_int($value)) return $value;
        if(is_string($value) && ctype_digit($value)) return (int) $value;
        return null;
    }

    protected function getResourceModel(): ?Model
    {
        $value = $this->getResourceValue();
        if($value instanceof Model) return $value;

        $value = $this->getResourceId();
        if(is_int($value)) {
            $modelNamespace = config('api.namespace_models', 'App\Models');
            $requestNamespace = config('api.namespace_requests', 'App\Http\Requests');
            $modelClass = str_replace($requestNamespace, $modelNamespace, static::class);
            $modelClass = Str::replaceLast('Request', '', $modelClass);
            $model = $modelClass::findOrFail($value);
            return $model;
        }
        return null;
    }

    protected function getResourceModelKey($key)
    {
        $model = $this->getResourceModel();
        if($model instanceof Model) {
            return $model->{$key};
        }
        return null;
    }
}