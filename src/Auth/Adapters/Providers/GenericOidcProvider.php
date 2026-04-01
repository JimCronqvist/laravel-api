<?php

namespace Cronqvist\Api\Auth\SSO\Adapters\Providers;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class GenericOidcProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['openid', 'profile', 'email'];

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';


    protected function getAuthUrl($state)
    {
        // TODO: Implement getAuthUrl() method.
    }

    protected function getTokenUrl()
    {
        // TODO: Implement getTokenUrl() method.
    }

    protected function getUserByToken($token)
    {
        // TODO: Implement getUserByToken() method.
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['sub'],
            'nickname' => null,
            'name' => $user['name'],
            'email' => $user['email'] ?? null,
            'avatar' => $user['picture'] ?? null,
        ]);
    }
}
