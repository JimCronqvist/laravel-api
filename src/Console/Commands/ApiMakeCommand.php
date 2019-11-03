<?php

namespace Cronqvist\Api\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class ApiMakeCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:api';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:api {name} {--seeder} {--factory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new model, controller, api resource, seeder and factory in one go!';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->registerStyles();

        $name = ucfirst($this->argument('name'));

        // User model, seeder and factory is created by Laravel by default
        if($name != 'User') {
            $this->call('make:model', [
                'name' => 'Models/'.$name,
                '-m' => true
            ]);

            if($this->option('seeder')) {
                $this->call('make:seeder', [
                    'name' => Str::plural($name).'TableSeeder'
                ]);
                $this->line('Please add the new seeder to your DatabaseSeeder.php file', 'important');
                $this->line('$this->call('.Str::plural($name).'TableSeeder::class);', 'code');
                $this->line(PHP_EOL);
            }

            if($this->option('factory')) {
                $this->call('make:factory', [
                    'name' => $name.'Factory',
                    '--model' => 'Models/'.$name
                ]);
            }
        }

        // Use custom version of 'make:controller' for APIs instead, with pre-filled php code.
        $this->call('make:apiController', [
            'name' => $name
        ]);
        $this->call('make:apiResource', [
            'name' => $name.'Resource'
        ]);
        $this->call('make:apiRequest', [
            'name' => $name.'Request'
        ]);
        $this->call('make:apiPolicy', [
            'name' => Str::singular($name).'Policy',
            '--model' => 'Models/'.$name
        ]);

        $this->line('Please add the new routes in routes/api.php', 'important');
        $this->line("Route::apiResource('".Str::kebab(Str::plural($name))."', '".$name."Controller');", 'code');
    }

    /**
     * Register styles for the artisan commands
     *
     * @return void
     */
    protected function registerStyles()
    {
        $style = new OutputFormatterStyle('yellow', 'black', ['bold']);
        $this->output->getFormatter()->setStyle('important', $style);

        $style = new OutputFormatterStyle('cyan', 'black', ['bold']);
        $this->output->getFormatter()->setStyle('code', $style);
    }
}
