<?php

namespace Cronqvist\Api\Auth\SSO\Services\Oidc;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OidcJwtValidator
{
    public function validate(string $idToken, array $config): array
    {
        $jwks = $this->jwks($config['issuer']);
        $decoded = (array) JWT::decode($idToken, JWK::parseKeySet($jwks));

        // Basic checks
        if($decoded['aud'] !== $config['client_id']) {
            abort(403, 'Invalid audience');
        }
        if($decoded['iss'] !== $config['issuer']) {
            abort(403, 'Invalid issuer');
        }
        if($decoded['exp'] < time()) {
            abort(403, 'Token expired');
        }

        return $decoded;
    }

    protected function jwks(string $issuer): array
    {
        return Cache::remember(
            'sso:oidc:jwks:'.$issuer,
            3600,
            fn () => Http::get(rtrim($issuer, '/').'/.well-known/jwks.json')->json(),
        );
    }
}
