<?php

namespace Cronqvist\Api\Auth\SSO\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Http;

class OAuthTokenService
{
    public function issueForUser(Authenticatable $user): array
    {
        //app(AuthService::class)->loginAs($user);
        dd('Look at AuthService::loginAs, and see if that can be done differently.');

        //
        // Below is AI generated boilerplate that we probably will throw away.
        //
        $response = Http::asForm()->post(
            config('app.url') . '/oauth/token',
            [
                'grant_type' => 'password',
                'client_id' => config('passport.password_client_id'),
                'client_secret' => config('passport.password_client_secret'),
                'username' => $user->email,
                'password' => '__SSO__',
                'scope' => '',
            ]
        );

        if (! $response->successful()) {
            throw new \Exception('Token issue failed');
        }

        return $response->json();
    }
}