<?php

namespace Cronqvist\Api\Auth\SSO\Services;

use Cronqvist\Api\Auth\SSO\Models\SsoDomain;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class SsoProviderResolver
{
    public function resolve(string $email, string $provider): array
    {
        $domainPart = Str::after($email, '@');

        $domain = SsoDomain::where('domain', $domainPart)->first();

        if(!$domain) {
            return $this->default($provider);
        }

        if(empty($domain->allowed_providers)) {
            throw new Exception('SSO not configured for domain');
        }

        if(!in_array($provider, $domain->allowed_providers)) {
            throw new Exception('Provider not allowed');
        }

        $custom = $domain->customProviders()
            ->where('sso_custom_providers.provider', $provider)
            ->first();

        if($custom) {
            return [
                ...$custom->extra_config,
                'client_id' => $custom->client_id,
                'client_secret' => $this->decryptIfEncrypted($custom->client_secret),
                'redirect' => $custom->redirect_uri,
                'scopes' => $custom->scopes,
                'issuer' => $custom->issuer,
            ];
        }

        return $this->default($provider);
    }

    protected function default(string $provider): array
    {
        $config = config("services.$provider");

        if(!$config) {
            throw new Exception("No global services config for [$provider]");
        }

        return $config;
    }

    protected function decryptIfEncrypted($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            return $value;
        }
    }
}