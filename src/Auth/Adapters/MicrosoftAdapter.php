<?php

namespace Cronqvist\Api\Auth\SSO\Adapters;

use Laravel\Socialite\Contracts\Provider;

class MicrosoftAdapter extends AbstractSocialiteAdapter
{
    public function build(array $config): Provider
    {
        return $this->buildProvider(\SocialiteProviders\Microsoft\Provider::class, $config);
    }
}
