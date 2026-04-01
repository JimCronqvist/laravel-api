<?php

namespace Cronqvist\Api\Auth\SSO\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Exception;

class SsoCodeService
{
    protected int $ttl;

    public function __construct(int $ttlSeconds = 60)
    {
        $this->ttl = $ttlSeconds;
    }

    /**
     * Create one-time SSO exchange code
     */
    public function create($user, Request $request): string
    {
        $code = Str::random(64);

        Cache::put($this->key($code), [
            'user_id' => $user->id,
            'ip' => $request->ip(),
            'ua' => $request->userAgent(),
            'ts' => now()->timestamp,
        ], now()->addSeconds($this->ttl));

        return $code;
    }

    /**
     * Consume code (one-time)
     */
    public function consume(string $code, Request $request): int
    {
        $data = Cache::pull($this->key($code));

        if(!$data) {
            throw new Exception('Invalid or expired SSO code');
        }

        // Security checks
        if((now()->timestamp - $data['ts']) > $this->ttl) {
            throw new Exception('Code expired');
        }
        if($data['ip'] !== $request->ip()) {
            throw new Exception('IP mismatch');
        }
        if($data['ua'] !== $request->userAgent()) {
            throw new Exception('User agent mismatch');
        }

        return $data['user_id'];
    }

    protected function key(string $code): string
    {
        return "sso_code:$code";
    }
}