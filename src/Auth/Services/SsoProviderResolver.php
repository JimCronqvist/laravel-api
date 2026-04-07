<?php

namespace Cronqvist\Api\Auth\SSO\Services;

use Cronqvist\Api\Auth\SSO\Models\SsoDomain;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;

class SsoProviderResolver
{
    public function resolve(?string $domain, string $provider): array
    {
        if(!$domain) {
            return $this->default($provider);
        }

        $ssoDomain = SsoDomain::query()
            ->where('domain', $domain)
            ->where('verified', 1)
            ->first();
        if(!$ssoDomain) {
            return $this->default($provider);
        }

        if(empty($ssoDomain->allowed_providers)) {
            throw new Exception('SSO not configured for domain');
        }

        if(!in_array($provider, $ssoDomain->allowed_providers)) {
            throw new Exception('Provider not allowed');
        }

        $custom = $ssoDomain->customProviders()
            ->where('sso_custom_providers.provider', $provider)
            ->first();

        if($custom) {
            $default = $this->default($provider, false);
            return [
                ...($custom->extra_config ?? []),
                'client_id' => $custom->client_id ?? Arr::get($default, 'client_id'),
                'client_secret' => $this->decryptIfEncrypted($custom->client_secret) ?? Arr::get($default, 'client_secret'),
                'redirect' => $custom->redirect_uri ?? Arr::get($default, 'redirect'),
                'scopes' => $custom->scopes ?? Arr::get($default, 'scopes', []),
                'issuer' => $custom->issuer ?? Arr::get($default, 'issuer'),
            ];
        }

        return $this->default($provider);
    }

    protected function default(string $provider, $throwIfNotFound = true): array
    {
        $config = config("services.$provider", []);

        if(!$config && $throwIfNotFound) {
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