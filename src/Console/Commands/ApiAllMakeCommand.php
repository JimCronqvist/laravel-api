<?php

namespace Cronqvist\Api\Console\Commands;

use Cronqvist\Api\Services\Helpers\HelperService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ApiAllMakeCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:apiAll';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:apiAll {prefix?} {--seeder}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create APIs for all existing models.';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $prefix = $this->hasArgument('prefix') ? $this->argument('prefix') : null;

        $models = HelperService::getAllModelInstances();
        foreach($models as $model) {
            if($model instanceof Pivot) continue;

            $name = str_replace('App\Models\\', '', get_class($model));
            $name = str_replace('App\User', 'User', $name); // Handle the default User class from Laravel
            $name = str_replace('\\', '/', $name);

            if($prefix) {
                // Filter out something here...
            }

            $this->line("Generate API for table '" . $model->getTable() . "' (" . get_class($model) . ')');
            $this->call('make:api', [
                'name' => $name,
                '--seeder' => (bool) $this->option('seeder'),
            ]);
        }
    }
}
