<?php

namespace Cronqvist\Api\Auth\SSO\Services;

use Illuminate\Support\Facades\Crypt;
use Exception;

class SsoStateService
{
    protected int $ttl;

    public function __construct(int $ttlSeconds = 300)
    {
        $this->ttl = $ttlSeconds;
    }

    /**
     * Encode state for redirect
     */
    public function encode(array $data): string
    {
        $payload = array_merge($data, [
            'ts' => now()->timestamp
        ]);

        return Crypt::encryptString(json_encode($payload));
    }

    /**
     * Decode and validate state
     */
    public function decode(string $state): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($state), true);
        } catch(\Throwable $e) {
            throw new Exception('Invalid state payload');
        }

        if(!isset($payload['ts'])) {
            throw new Exception('State missing timestamp');
        }

        if($this->isExpired($payload['ts'])) {
            throw new Exception('State expired');
        }

        return $payload;
    }

    /**
     * Check expiration
     */
    protected function isExpired(int $timestamp): bool
    {
        return (now()->timestamp - $timestamp) > $this->ttl;
    }
}