<?php

namespace Cronqvist\Tenancy;

use Cronqvist\Tenancy\Facades\Tenant;
use Cronqvist\Tenancy\Middleware\TenantMiddleware;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Tenant::class); // Singleton that is reset per request lifecycle automatically
        $this->telescopeTag();
        $this->registerMiddlewareAliases();
    }

    public function registerMiddlewareAliases()
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('tenant', TenantMiddleware::class);
    }

    public function telescopeTag()
    {
        if(!config('telescope.enabled') || !class_exists(Telescope::class)) return;

        Telescope::tag(function(IncomingEntry $entry) {
            if(!request()->route()) return [];
            return Tenant::isInitialized() ? ['Tenant:' . Tenant::getTenantId()] : [];
        });
    }
}
