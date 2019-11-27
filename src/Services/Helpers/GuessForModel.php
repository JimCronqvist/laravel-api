<?php

namespace Cronqvist\Api\Services\Helpers;

trait GuessForModel
{
    /**
     * Guess the Policy class for the given model
     *
     * @param string $modelClass
     * @return string
     */
    protected function guessPolicyClassFor($modelClass)
    {
        return str_replace(
            config('api.namespace_models', 'App\Models'),
            config('api.namespace_policies', 'App\Policies'),
            $modelClass
        ) . 'Policy';
    }

    /**
     * Guess the Resource class for the given model
     *
     * @param string $modelClass
     * @return string
     */
    protected function guessResourceClassFor($modelClass)
    {
        return str_replace(
            config('api.namespace_models', 'App\Models'),
            config('api.namespace_resources', 'App\Http\Resources'),
            $modelClass
        ) . 'Resource';
    }

    /**
     * Guess the Service class for the given model
     *
     * @param string $modelClass
     * @return string
     */
    protected function guessServiceClassFor($modelClass)
    {
        return str_replace(
            config('api.namespace_models', 'App\Models'),
            config('api.namespace_services', 'App\Services\Api'),
            $modelClass
        ) . 'Service';
    }

    /**
     * Guess the FormRequest class for the given model
     *
     * @param string $modelClass
     * @return string
     */
    protected function guessFormRequestClassFor($modelClass)
    {
        return str_replace(
            config('api.namespace_models', 'App\Models'),
            config('api.namespace_requests', 'App\Http\Requests'),
            $modelClass
        ) . 'Request';
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
