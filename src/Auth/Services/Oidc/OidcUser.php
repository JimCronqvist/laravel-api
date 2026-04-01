<?php

namespace Cronqvist\Api\Auth\SSO\Services\Oidc;

use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\AbstractUser;

class OidcUser extends AbstractUser implements SocialiteUser
{
    public function __construct(array $claims)
    {
        $this->setRaw($claims)->map([
            'id' => $claims['sub'],
            'email' => $claims['email'] ?? null,
            'name' => $claims['name'] ?? null,
            'nickname' => null,
            'avatar' => null,
        ]);
    }
}
