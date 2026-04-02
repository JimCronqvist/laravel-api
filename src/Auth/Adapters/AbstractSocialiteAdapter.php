<?php

namespace Cronqvist\Api\Auth\SSO\Adapters;

use Cronqvist\Api\Auth\SSO\Adapters\Contracts\SsoProviderAdapter;
use Illuminate\Support\Arr;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\Config as SocialiteProvidersConfig;
use SocialiteProviders\Manager\ConfigTrait as SocialiteProvidersConfigTrait;

abstract class AbstractSocialiteAdapter implements SsoProviderAdapter
{
    protected function buildProvider(string $providerClass, array $config)
    {
        $driver = Socialite::buildProvider($providerClass, $config);

        if(
            method_exists($driver, 'setConfig')
            && in_array(SocialiteProvidersConfigTrait::class, class_uses_recursive($driver))
            && method_exists($providerClass, 'additionalConfigKeys')
        ) {
            $driver->setConfig(new SocialiteProvidersConfig(
                $config['client_id'],
                $config['client_secret'],
                $config['redirect'],
                Arr::only($config, $providerClass::additionalConfigKeys())
            ));
        }

        return $driver;
    }
}