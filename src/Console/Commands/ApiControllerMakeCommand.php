<?php

namespace Cronqvist\Api\Console\Commands;

use Illuminate\Routing\Console\ControllerMakeCommand;

class ApiControllerMakeCommand extends ControllerMakeCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:apiController';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new API controller class';


    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function getNameInput()
    {
        return trim($this->argument('name')) . 'Controller';
    }

    /**
     * Get the value of a command option.
     *
     * @param  string|null  $key
     * @return string|array|bool|null
     */
    public function option($key = null)
    {
        if($key == 'model') return 'Models/'.str_replace('Controller', '', $this->getNameInput());
        if($key == 'api') return true;
        return parent::option($key);
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Http\Controllers\Api';
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__ . '/stubs/controller.model.api.stub';
    }
}
