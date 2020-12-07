<?php

namespace Cronqvist\Api\Console\Commands;

use Illuminate\Foundation\Console\PolicyMakeCommand as BasePolicyMakeCommand;
use Symfony\Component\Console\Input\InputOption;

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
     * Execute the console command.
     *
     * @return bool|null
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        if(!$this->option('model')) {
            $this->error('Not enough arguments (missing: "--model").');
            return 0;
        }

        return parent::handle();
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
        $model = app()->getNamespace() . 'Models\\' . str_replace('/', '\\', $model);
        $table = (new $model)->getTable();
        return str_replace('DummyTable', $table, $stub);
    }

    /**
     * Get the model for the guard's user provider.
     *
     * @return string|null
     */
    protected function userProviderModel()
    {
        $config = $this->laravel['config'];
        $guard = 'api';
        return $config->get('auth.providers.'.$config->get('auth.guards.'.$guard.'.provider').'.model');
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getOptions()
    {
        $type = strtolower($this->type);

        return [
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The model that the ' . $type . ' applies to'],
        ];
    }
}
