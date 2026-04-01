<?php

namespace Cronqvist\Api\Auth\Adapters;

use Cronqvist\Api\Auth\SSO\Adapters\Contracts\SsoProviderAdapter;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;

class MicrosoftAdapter implements SsoProviderAdapter
{
    public function build(array $config): Provider
    {
        return Socialite::buildProvider(\SocialiteProviders\Microsoft\Provider::class, $config);
    }
}
