<?php

namespace Cronqvist\Api\Services\Helpers;

use Illuminate\Support\Pluralizer;

trait GuessForModel
{
    /**
     * Guess the class for the given model. Not to be called directly.
     *
     * @param string $type
     * @param string $modelClass
     * @return string
     */
    protected function guessClassFor($type, $modelClass)
    {
        $plural = strtolower(Pluralizer::plural($type));
        $modelNamespace = config('api.namespace_models', 'App\Models');
        if($modelClass === 'App\\User') {
            $modelNamespace = 'App';
        }
        $typeNamespace = config('api.namespace_' . strtolower($plural), 'App\\' . $plural);
        return str_replace($modelNamespace, $typeNamespace, $modelClass) . $type;
    }

    /**
     * Guess the Policy class for the given model
     *
     * @param string $modelClass
     * @return string
     */
    protected function guessPolicyClassFor($modelClass)
    {
        return $this->guessClassFor('Policy', $modelClass);
    }

    /**
     * Guess the Resource class for the given model
     *
     * @param string $modelClass
     * @return string
     */
    protected function guessResourceClassFor($modelClass)
    {
        return $this->guessClassFor('Resource', $modelClass);
    }

    /**
     * Guess the Service class for the given model
     *
     * @param string $modelClass
     * @return string
     */
    protected function guessServiceClassFor($modelClass)
    {
        return $this->guessClassFor('Service', $modelClass);
    }

    /**
     * Guess the FormRequest class for the given model
     *
     * @param string $modelClass
     * @return string
     */
    protected function guessFormRequestClassFor($modelClass)
    {
        return $this->guessClassFor('Request', $modelClass);
    }

    /**
     * Build a FormRequest class instance for the given model
     *
     * @param string $modelClass
     * @return \Illuminate\Foundation\Http\FormRequest
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function resolveFormRequestFor($modelClass)
    {
        return app()->make($this->guessFormRequestClassFor($modelClass));
    }

    /**
     * Build a Policy class instance for the given model
     *
     * @param string $modelClass
     * @return \Cronqvist\Api\Policies\Policy
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function resolvePolicyFor($modelClass)
    {
        return app()->make($this->guessPolicyClassFor($modelClass));
    }
}
