<?php

namespace Cronqvist\Api\Http\Requests;

use Cronqvist\Api\Exception\ApiException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;

trait TransformRules
{
    /**
     * Properties that never should be validated and allowed
     *
     * @var array
     */
    protected $excludeProperties = ['id', 'created_at', 'updated_at', 'deleted'];


    /**
     * Helper method to temporarily disable the input validation for rapid development in non-production environments
     *
     * @return array
     * @throws ApiException
     */
    protected function disableInputValidationForNow()
    {
        if(!App::environment('local')) {
            $class = get_class($this);
            throw new ApiException('Input validation is disabled. This is not allowed in non-local environments! In: ' . $class);
        }

        $rules = [];
        foreach(Arr::except(request()->post(), $this->excludeProperties) as $key => $value) {
            $rules[$key] = 'sometimes';
        }
        return $rules;
    }
}