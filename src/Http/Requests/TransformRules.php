<?php

namespace Cronqvist\Api\Http\Requests;

use Cronqvist\Api\Exception\ApiException;
use Illuminate\Support\Facades\App;

trait TransformRules
{
    /**
     * Helper method to temporarily disable the input validation for rapid development in non-production environments
     *
     * @return array
     * @throws ApiException
     */
    protected function disableInputValidationForNow()
    {
        if(App::environment('production')) {
            $class = get_class($this);
            throw new ApiException('Input validation is disabled, this is not allowed in production! In: ' . $class);
        }
        return ['*' => 'sometimes'];
    }
}