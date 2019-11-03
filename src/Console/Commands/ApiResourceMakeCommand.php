<?php

namespace Cronqvist\Api\Console\Commands;

use Illuminate\Foundation\Console\ResourceMakeCommand as BaseResourceMakeCommand;

class ApiResourceMakeCommand extends BaseResourceMakeCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:apiResource';


    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/stubs/resource.stub';
    }
}
