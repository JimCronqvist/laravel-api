<?php

namespace Cronqvist\Api\Auth\SSO\Traits;

trait HasSso
{
    public function isSsoBound(): bool
    {
        return !empty($this->sso_provider) && !empty($this->sso_provider_id);
    }

    public function bindSso(string $provider, string $providerId): void
    {
        $this->update([
            'sso_provider' => $provider,
            'sso_provider_id' => $providerId,
        ]);
    }

    public function resetSso(): void
    {
        $this->update([
            'sso_provider' => null,
            'sso_provider_id' => null,
        ]);
    }
}