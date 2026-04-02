<?php

namespace Cronqvist\Api\Auth\SSO\Adapters;

use Laravel\Socialite\Contracts\Provider;

class GoogleAdapter extends AbstractSocialiteAdapter
{
    public function build(array $config): Provider
    {
        return $this->buildProvider(\Laravel\Socialite\Two\GoogleProvider::class, $config);
    }
}
