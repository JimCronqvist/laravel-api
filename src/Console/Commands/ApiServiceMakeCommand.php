<?php

namespace Cronqvist\Api\Console\Commands;

class ApiServiceMakeCommand extends ApiPolicyMakeCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:apiService';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a Service class for a Model to be used in a Controller.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Service';


    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return config('api.stub_service', __DIR__.'/stubs/service.stub');
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Services\Api';
    }
}
