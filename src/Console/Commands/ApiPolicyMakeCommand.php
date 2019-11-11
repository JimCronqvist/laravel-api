<?php

namespace Cronqvist\Api\Console\Commands;

use Illuminate\Foundation\Console\PolicyMakeCommand as BasePolicyMakeCommand;

class ApiPolicyMakeCommand extends BasePolicyMakeCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:apiPolicy';


    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return config('api.stub_policy', __DIR__.'/stubs/policy.stub');
    }

    /**
     * Replace the model for the given stub.
     *
     * @param  string  $stub
     * @param  string  $model
     * @return string
     */
    protected function replaceModel($stub, $model)
    {
        $stub = parent::replaceModel($stub, $model);
        $model = app()->getNamespace() . str_replace('/', '\\', $model);
        $table = (new $model)->getTable();
        return str_replace('DummyTable', $table, $stub);
    }
}
