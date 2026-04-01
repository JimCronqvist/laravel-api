<?php

namespace Cronqvist\Api\Auth\Adapters;

use Cronqvist\Api\Auth\SSO\Adapters\Contracts\SsoProviderAdapter;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;

class OktaAdapter implements SsoProviderAdapter
{
    public function build(array $config): Provider
    {
        return Socialite::buildProvider(\SocialiteProviders\Okta\Provider::class, $config);
    }
}
