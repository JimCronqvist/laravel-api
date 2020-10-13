<?php

namespace Cronqvist\Api;

use Cronqvist\Api\Console\Commands\ApiAllMakeCommand;
use Cronqvist\Api\Console\Commands\ApiControllerMakeCommand;
use Cronqvist\Api\Console\Commands\ApiCreatePersonalAccessToken;
use Cronqvist\Api\Console\Commands\ApiMakeCommand;
use Cronqvist\Api\Console\Commands\ApiPolicyMakeCommand;
use Cronqvist\Api\Console\Commands\ApiResourceMakeCommand;
use Cronqvist\Api\Console\Commands\ApiRequestMakeCommand;
use Cronqvist\Api\Console\Commands\ApiServiceMakeCommand;
use Cronqvist\Api\Http\Middleware\AccessTokenCookieMiddleware;
use Cronqvist\Api\Http\Middleware\ApiGuardMiddleware;
use Cronqvist\Api\Http\Middleware\JsonMiddleware;
use Cronqvist\Api\Services\Helpers\GuessForModel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ApiServiceProvider extends BaseServiceProvider
{
    use GuessForModel;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // php artisan vendor:publish --tag=api
        $this->publishes([
            __DIR__ . '/config/api.php' => config_path('api.php'),
            __DIR__ . '/Http/Controllers/stubs/ApiController.stub' => app_path('Http/Controllers/ApiController.php'),
            __DIR__ . '/Policies/stubs/Policy.stub' => app_path('Policies/Policy.php'),
        ], 'api');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ApiAllMakeCommand::class,
                ApiControllerMakeCommand::class,
                ApiMakeCommand::class,
                ApiPolicyMakeCommand::class,
                ApiResourceMakeCommand::class,
                ApiRequestMakeCommand::class,
                ApiServiceMakeCommand::class,
                ApiCreatePersonalAccessToken::class,
            ]);
        }

        $this->registerMiddlewareAliases();
        $this->registerPolicies();
    }

    /**
     * Register the middleware
     *
     * @return void
     */
    protected function registerMiddlewareAliases()
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('api.guard', ApiGuardMiddleware::class);
        $router->aliasMiddleware('api.json', JsonMiddleware::class);
        $router->aliasMiddleware('api.accessTokenCookie', AccessTokenCookieMiddleware::class);

        // Let the developer prepend the middleware by themselves instead
        //$router->prependMiddlewareToGroup('api', 'api.guard');
        //$router->prependMiddlewareToGroup('api', 'api.json');
        //$router->prependMiddlewareToGroup('api', 'api.accessTokenCookie');
    }

    /**
     * Initialize the policies. Set policy naming conventions and allow for a super admin to bypass the policies.
     *
     * @return void
     */
    protected function registerPolicies()
    {
        // Map the User::class to UserPolicy::class, as that does not match the below naming structure
        // User.php is not located in the Models folder
        Gate::policy('App\User', config('api.namespace_policies', 'App\Policies') . '\UserPolicy');

        // Change the naming convention for Laravel to look for Policy classes (default does not assume a "Models" dir)
        Gate::guessPolicyNamesUsing(function($modelClass) {
            return $this->guessPolicyClassFor($modelClass);
        });

        // If you are a "Super Admin", always pass the authorization checks
        Gate::after(function ($user, $ability, $result, $arguments) {
            if($result !== true && method_exists($user, 'isSuperAdmin')) {
                if($user->isSuperAdmin()) {
                    // Perhaps also log something on the request here in the future to know if this was overridden...
                    return true;
                }
            }
        });
    }

    /**
     * Load the routes from a file
     *
     * @param string $name
     * @return void
     */
    protected static function registerRoutes($name)
    {
        (new self(app()))->loadRoutesFrom(__DIR__ . '/routes/' . $name . '.php');
    }

    /**
     * Load the 'merge' routes
     *
     * @return void
     */
    public static function registerMergeRoutes()
    {
        self::registerRoutes('merge');
    }

    /**
     * Load the 'auth' routes
     *
     * @return void
     */
    public static function registerAuthRoutes()
    {
        self::registerRoutes('auth');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/config/api.php', 'api');
    }
}