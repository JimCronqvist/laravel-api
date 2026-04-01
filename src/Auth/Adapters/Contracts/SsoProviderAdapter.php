<?php

namespace Cronqvist\Api\Auth\SSO\Adapters\Contracts;

/**
 * Interface SsoProviderAdapter
 *
 * Used to build the SSO provider based on the configuration provided. This is required to allow non-static
 * config of the SSO providers from Socialite. I.e. Multi-tenant SSO.
 *
 * The adapter should return an instance of the SSO provider, specifically what the Socialite provider does.
 *
 * Community providers sometimes does more than just buildProvider, hence why this is needed.
 *
 * @package Cronqvist\Api\Auth\SSO\Adapters\Contracts
 */
interface SsoProviderAdapter
{
    public function build(array $config);
}
