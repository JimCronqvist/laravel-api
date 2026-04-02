<?php

namespace Cronqvist\Api\Auth\SSO\Adapters;

use Cronqvist\Api\Auth\SSO\Adapters\Providers\GenericOidcProvider;
use Laravel\Socialite\Contracts\Provider;

class OidcAdapter extends AbstractSocialiteAdapter
{
    public function build(array $config): Provider
    {
        //return new OidcClient($config, $email); // @todo wip
        //return new GenericOidcProvider(request(), $config['client_id'], $config['client_secret'], $config['redirect']);
    }
}