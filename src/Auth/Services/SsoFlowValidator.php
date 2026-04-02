<?php

namespace Cronqvist\Api\Auth\SSO\Services;

class SsoFlowValidator
{
    public function validateState(array $state, string $provider, array $config): void
    {
        if($provider !== $state['provider']) {
            abort(403, 'Provider mismatch');
        }

        if($config['client_id'] !== $state['client_id']) {
            abort(403, 'Client mismatch');
        }
    }

    public function validateEmail(string $email, array $state): void
    {
        if($state['email'] !== null && $email !== $state['email']) {
            abort(403, 'Email mismatch');
        }
    }

    public function validateDomain(string $domain, array $state): void
    {
        if($state['domain'] !== null && $domain !== $state['domain']) {
            abort(403, 'Domain mismatch');
        }
    }

    public function validateNonce(string $idToken, array $state, array $config): void
    {
        if(!$idToken) {
            abort(403, 'Missing id_token');
        }

        $decoded = $this->decodeJwt($idToken);
        if(($decoded['nonce'] ?? null) !== ($state['nonce'] ?? null)) {
            abort(403, 'Invalid nonce');
        }
    }

    public function shouldValidateNonce(array $config): bool
    {
        return isset($config['issuer']) || ($config['oidc'] ?? false);
    }

    protected function decodeJwt(string $token): array
    {
        [$header, $payload] = explode('.', $token);

        return json_decode(base64_decode($payload), true);
    }
}
