<?php

namespace Cronqvist\Api\Auth\SSO;

use Cronqvist\Api\Auth\SSO\Adapters\MicrosoftAdapter;
use Cronqvist\Api\Auth\SSO\Adapters\OktaAdapter;
use Cronqvist\Api\Auth\SSO\Adapters\GoogleAdapter;
use Cronqvist\Api\Auth\SSO\Adapters\SocialiteFactory;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class SsoServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SocialiteFactory::class, function () {
            $factory = new SocialiteFactory();

            $factory->register('google', new GoogleAdapter());
            if(class_exists(\SocialiteProviders\Microsoft\Provider::class)) {
                $factory->register('microsoft', new MicrosoftAdapter());
            }
            if(class_exists(\SocialiteProviders\Okta\Provider::class)) {
                $factory->register('okta', new OktaAdapter());
            }

            return $factory;
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/sso.php' => config_path('sso.php'),
        ], 'sso');

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /**
     * Load the 'auth' routes
     *
     * @return void
     */
    public static function registerSsoRoutes()
    {
        self::registerRoutes('sso');
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
}

