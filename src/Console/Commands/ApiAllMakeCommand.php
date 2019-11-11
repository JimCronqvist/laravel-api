<?php

namespace Cronqvist\Api\Console\Commands;

use Cronqvist\Api\Services\Helpers\HelperService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Str;

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
    protected $signature = 'make:apiAll {match} {--seeder} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create APIs for all existing models.';

    /**
     * Filter out known tables, that are coming from third party packages
     *
     * @var array
     */
    protected $excludeTables = [
        // Laravel
        'password_resets', 'failed_jobs', 'migrations',

        // Laravel Passport
        'oauth_access_tokens', 'oauth_auth_codes', 'oauth_clients', 'oauth_personal_access_clients',
        'oauth_refresh_tokens',

        // Laravel Telescope
        'telescope_entries', 'telescope_entries_tags', 'telescope_monitoring',

        // Spatie - Laravel Permissions
        'model_has_permissions', 'model_has_roles', 'role_has_permissions', 'roles', 'permissions',
    ];


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $models = collect(HelperService::getAllModelInstances())->mapWithKeys(function($model){
            return [$model->getTable() => $model];
        })->all();

        $tables = HelperService::getAllTablesMatching($this->argument('match'));
        $tables = collect($tables)
            ->diff($this->excludeTables)
            ->values()
            ->flip()
            ->transform(function($value, $table) use($models) {
                return key_exists($table, $models) ? $models[$table] : null;
            })
            ->all();

        dump(array_keys($tables));

        foreach($tables as $table => $model) {
            if($model instanceof Pivot) continue;

            $class = $model === null
                ? config('api.namespace_models', 'App\Models') . '\\' . Str::studly(Str::singular($table))
                : get_class($model);
            $name = str_replace(config('api.namespace_models', 'App\Models') .'\\', '', $class);
            $name = str_replace('App\User', 'User', $name); // Handle the default User class from Laravel
            $name = str_replace('\\', '/', $name);

            $this->line("Generate API for table '" . $table . "' (" . $class . ')');
            $this->alert('php artisan make:api ' . $name . ($this->option('seeder') ? ' --seeder' : ''));

            if($this->option('dry-run')) continue;

            $this->call('make:api', [
                'name' => $name,
                '--seeder' => (bool) $this->option('seeder'),
            ]);
        }
    }
}
