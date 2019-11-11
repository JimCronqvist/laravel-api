<?php

namespace Cronqvist\Api\Console\Commands;

use Cronqvist\Api\Services\Helpers\GuessForModel;
use Illuminate\Routing\Console\ControllerMakeCommand;
use Symfony\Component\Console\Input\InputOption;

class ApiControllerMakeCommand extends ControllerMakeCommand
{
    use GuessForModel;

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
        if($this->option('service')) {
            return config('api.stub_controller_service', __DIR__ . '/stubs/controller.model.api.service.stub');
        }
        return config('api.stub_controller', __DIR__ . '/stubs/controller.model.api.stub');
    }

    /**
     * Execute the console command.
     *
     * @return null
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        parent::handle();

        $name = trim($this->argument('name'));

        if($this->option('service')) {
            $this->call('make:apiService', [
                'name' => $name . 'Service',
                '-m' => 'Models/' . $name,
            ]);
        }
    }

    /**
     * Build the class with the given name.
     *
     * Remove the base controller import if we are already in base namespace.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        $modelClass = $this->parseModel($this->option('model'));

        $replace = [
            'DummyFullResourceClass' => $this->guessResourceClassFor($modelClass),
            'DummyFullServiceClass' => $this->guessServiceClassFor($modelClass),
            'DummyFullRequestClass' => $this->guessFormRequestClassFor($modelClass),
        ];

        $stub = str_replace(array_keys($replace), array_values($replace), $stub);

        return $stub;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array_merge(parent::getOptions(), [
            ['service', null, InputOption::VALUE_NONE, 'Generate a service class for the controller class.'],
        ]);
    }
}
