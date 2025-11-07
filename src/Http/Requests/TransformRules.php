<?php

namespace Cronqvist\Api\Http\Requests;

use Cronqvist\Api\Exception\ApiException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;

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


    /**
     * Add a rule (e.g. 'sometimes') to all fields, unless the field is in $skipFields or already has it.
     *
     * @param array $rules The existing validation rules.
     * @param \Illuminate\Validation\Rule|string $ruleToAdd The rule to add (string or Rule object).
     * @param array $skipFields Fields to skip adding the rule to.
     * @return array The modified validation rules.
     */
    protected function addRuleToAll(array $rules, $ruleToAdd, array $skipFields = []): array
    {
        return collect($rules)->map(function ($rule, $key) use ($ruleToAdd, $skipFields) {
            if(in_array($key, $skipFields, true)) return $rule;

            if(is_string($rule)) {
                $normalized = preg_replace('/\|+/', '|', trim($rule, '| '));
                $parts = array_filter(explode('|', $normalized));

                if (in_array($ruleToAdd, $parts, true)) {
                    return implode('|', $parts);
                }

                array_unshift($parts, $ruleToAdd);
                return implode('|', $parts);
            }
            else if(is_array($rule)) {
                if(in_array($ruleToAdd, $rule, true)) return $rule;

                array_unshift($rule, $ruleToAdd);
                return $rule;
            }

            return [$ruleToAdd, $rule];
        })->toArray();
    }

    /**
     * Add 'sometimes' to all fields, unless the field is in $skipFields. Useful for PUT/PATCH requests.
     *
     * @param array $rules
     * @param array $skipFields
     * @return array
     */
    protected function addSometimesToAll(array $rules, array $skipFields = []): array
    {
        return $this->addRuleToAll($rules, 'sometimes', $skipFields);
    }
}