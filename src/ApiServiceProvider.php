<?php

namespace Cronqvist\Api;

use Cronqvist\Api\Console\Commands\ApiAllMakeCommand;
use Cronqvist\Api\Console\Commands\ApiControllerMakeCommand;
use Cronqvist\Api\Console\Commands\ApiCreatePersonalAccessToken;
use Cronqvist\Api\Console\Commands\ApiMakeCommand;
use Cronqvist\Api\Console\Commands\ApiMediaCacheClean;
use Cronqvist\Api\Console\Commands\ApiPolicyMakeCommand;
use Cronqvist\Api\Console\Commands\ApiResourceMakeCommand;
use Cronqvist\Api\Console\Commands\ApiRequestMakeCommand;
use Cronqvist\Api\Console\Commands\ApiServiceMakeCommand;
use Cronqvist\Api\Http\Middleware\AccessTokenCookieMiddleware;
use Cronqvist\Api\Http\Middleware\ApiGuardMiddleware;
use Cronqvist\Api\Http\Middleware\JsonMiddleware;
use Cronqvist\Api\Services\Helpers\AccessInstance;
use Cronqvist\Api\Services\Helpers\GuessForModel;
use Illuminate\Routing\PendingResourceRegistration;
use Illuminate\Routing\ResourceRegistrar;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
                ApiMediaCacheClean::class,
            ]);
        }

        $this->registerMiddlewareAliases();
        $this->registerPolicies();
        $this->registerRouterMediaMacro();
        $this->registerRouterNestedRoutesMacro();
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

    /**
     * Register a router macro to enable "Route::apiResource('resource', 'Controller')->withMediaRoutes();".
     *
     * @return void
     */
    public function registerRouterMediaMacro()
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app['router'];
        PendingResourceRegistration::macro('withMediaRoutes', function(array $options = []) use($router) {
            $only = ['index', 'show', 'store', 'update', 'destroy'];
            if(isset($options['except'])) {
                $only = array_diff($only, (array) $options['except']);
            }

            $pending = $router->resource($this->name . '.media', $this->controller, array_merge([
                'only' => $only
            ], $options)); // + ['parameters' => ['media' => 'media']]

            AccessInstance::call($pending, function() use($router) {
                $this->registrar = new class($router) extends ResourceRegistrar {
                    protected function getResourceAction($resource, $controller, $method, $options)
                    {
                        return parent::getResourceAction($resource, $controller, 'media' . ucfirst($method), $options);
                    }
                };
            });
            return $pending;
        });
    }

    public function registerRouterNestedRoutesMacro()
    {
        $router = $this->app['router'];
        PendingResourceRegistration::macro('withNestedRelations', function(array $relations) use($router) {
            $base = $this->name; // First segment of the resource route path
            $controller = $this->controller;

            $currentGroupStack = $router->getGroupStack();
            $currentRouteGroupNamespace = optional(
                $currentGroupStack[array_key_last($currentGroupStack)] ?? null
            )['namespace'] ?? null;
            if($currentRouteGroupNamespace && is_string($controller) && !Str::startsWith($controller, '\\')) {
                $controller = $currentRouteGroupNamespace.'\\'.$controller;
            }
            // @todo Too heavy? Could we avoid instantiating the controller here? Guessing the model instead?
            $controllerInstance = app()->make($controller);
            $model = AccessInstance::getProperty($controllerInstance, 'modelClass');

            $resourceRegistrar = new ResourceRegistrar($router);
            $baseWildcard = $resourceRegistrar->getResourceWildcard($base);

            Route::scopeBindings()->group(function() use($relations, $base, $controller, $baseWildcard, $model, $resourceRegistrar) {
                foreach($relations as $key => $value) {
                    $relation = is_string($key) ? $key : $value;
                    $relationWildcard = $resourceRegistrar->getResourceWildcard($relation);
                    $relationType = (new $model)->{$relation}();
                    $relationName = Str::kebab($relation);

                    if($relationType instanceof HasMany) {
                        // one-to-many (HasMany) routes (parent owns children)
                        Route::get("$base/{{$baseWildcard}}/$relationName", [$controller, 'relationHasManyIndex'])->name($base.".$relationName.index")->defaults('relation', $relation);
                        Route::get("$base/{{$baseWildcard}}/$relationName/{{$relationWildcard}}", [$controller, 'relationHasManyShow'])->name($base.".$relationName.show")->defaults('relation', $relation);
                        // Use related controller on the below three endpoints? Or... support using the related controllers service class instead, could be enough?
                        Route::post("$base/{{$baseWildcard}}/$relationName", [$controller, 'relationHasManyStore'])->name($base.".$relationName.store")->defaults('relation', $relation);
                        Route::put("$base/{{$baseWildcard}}/$relationName/{{$relationWildcard}}", [$controller, 'relationHasManyUpdate'])->name($base.".$relationName.update")->defaults('relation', $relation);
                        Route::delete("$base/{{$baseWildcard}}/$relationName/{{$relationWildcard}}", [$controller, 'relationHasManyDestroy'])->name($base.".$relationName.destroy")->defaults('relation', $relation);
                    }
                    elseif($relationType instanceof HasOne) {
                        // one-to-one (HasOne) routes (parent own single child)
                        Route::get   ("$base/{{$baseWildcard}}/$relationName", [$controller, 'relationHasOneShow'])->name($base.".$relationName.show")->defaults('relation', $relation);
                        // Do we want separate POST and PUT methods, or just one UPSERT (PUT) method? Disable for now.
                        //Route::put   ("$base/{{$baseWildcard}}/$relationName", [$controller, 'relationHasOneUpsert'])->name($base.".$relationName.upsert")->defaults('relation', $relation);
                        Route::delete("$base/{{$baseWildcard}}/$relationName", [$controller, 'relationHasOneDestroy'])->name($base.".$relationName.destroy")->defaults('relation', $relation);
                    }
                    elseif($relationType instanceof BelongsTo) {
                        // one-to-one (BelongsTo) routes (read-only nested route to parent)
                        Route::get("$base/{{$baseWildcard}}/$relationName", [$controller, 'relationBelongsToShow'])->name($base.".$relationName.show")->defaults('relation', $relation);
                    }
                    elseif($relationType instanceof BelongsToMany) {
                        // many-to-many (BelongsToMany) (pivot attach/detach/sync)
                        Route::get("$base/{{$baseWildcard}}/$relationName", [$controller, 'relationBelongsToManyIndex'])->name($base.".$relationName.index")->defaults('relation', $relation);
                        Route::get("$base/{{$baseWildcard}}/$relationName/{{$relationWildcard}}", [$controller, 'relationBelongsToManyShow'])->name($base.".$relationName.show")->defaults('relation', $relation);
                        Route::post("$base/{{$baseWildcard}}/$relationName/{{$relationWildcard}}/attach", [$controller, 'relationBelongsToManyAttach'])->name($base.".$relationName.attach")->defaults('relation', $relation);
                        //Route::put("$base/{{$baseWildcard}}/$relationName/sync", [$controller, 'relationBelongsToManySync'])->name($base.".$relationName.sync")->defaults('relation', $relation);
                        Route::delete("$base/{{$baseWildcard}}/$relationName/{{$relationWildcard}}/detach", [$controller, 'relationBelongsToManyDetach'])->name($base.".$relationName.detach")->defaults('relation', $relation);
                        // Check if a pivot is used
                        if(method_exists($relationType, 'using')) {
                            Route::patch("$base/{{$baseWildcard}}/$relationName/{{$relationWildcard}}/pivot", [$controller, 'relationBelongsToManyPivot'])->name($base.".$relationName.updatePivot")->defaults('relation', $relation);
                        }
                    }
                }
            });

            return $this;
        });
    }
}