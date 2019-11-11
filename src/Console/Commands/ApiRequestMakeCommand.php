<?php

namespace Cronqvist\Api\Console\Commands;

use Illuminate\Foundation\Console\RequestMakeCommand as BaseRequestMakeCommand;

class ApiRequestMakeCommand extends BaseRequestMakeCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:apiRequest';


    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return config('api.stub_request', __DIR__.'/stubs/request.stub');
    }
}
