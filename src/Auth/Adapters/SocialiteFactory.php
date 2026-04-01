<?php

namespace Cronqvist\Api\Auth\SSO\Adapters;

use Cronqvist\Api\Auth\SSO\Adapters\Contracts\SsoProviderAdapter;
use Exception;

class SocialiteFactory
{
    protected array $adapters = [];

    public function register(string $provider, SsoProviderAdapter $adapter): void
    {
        $this->adapters[$provider] = $adapter;
    }

    public function driver(string $provider, array $config, ?string $email = null)
    {
        if(!isset($this->adapters[$provider])) {
            throw new Exception("SSO provider [$provider] is not supported");
        }

        return $this->adapters[$provider]->build($config, $email);
    }
}
