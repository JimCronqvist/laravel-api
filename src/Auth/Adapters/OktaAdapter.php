<?php

namespace Cronqvist\Api\Auth\SSO\Adapters;

use Laravel\Socialite\Contracts\Provider;

class OktaAdapter extends AbstractSocialiteAdapter
{
    public function build(array $config): Provider
    {
        return $this->buildProvider(\SocialiteProviders\Okta\Provider::class, $config);
    }
}
