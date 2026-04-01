<?php

namespace Cronqvist\Api\Auth\SSO\Adapters;

use Cronqvist\Api\Auth\SSO\Adapters\Contracts\SsoProviderAdapter;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;

class GoogleAdapter implements SsoProviderAdapter
{
    public function build(array $config): Provider
    {
        return Socialite::buildProvider(\Laravel\Socialite\Two\GoogleProvider::class, $config);
    }
}
