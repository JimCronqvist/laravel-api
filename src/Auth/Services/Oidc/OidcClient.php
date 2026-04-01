<?php

namespace Cronqvist\Api\Auth\SSO\Services\Oidc;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OidcClient
{
    protected array $config;
    protected ?string $email;

    public function __construct(array $config, ?string $email = null)
    {
        $this->config = $config;
        $this->email = $email;
    }

    public function redirect()
    {
        $discovery = $this->discovery();

        $params = [
            'client_id' => $this->config['client_id'],
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'redirect_uri' => $this->config['redirect'],
            'state' => request('state'),
            'nonce' => request('nonce'),
        ];

        if($this->email) {
            $params['login_hint'] = $this->email;
        }

        return redirect($discovery['authorization_endpoint'].'?'.http_build_query($params));
    }

    public function user()
    {
        $discovery = $this->discovery();

        $tokenResponse = Http::asForm()->post($discovery['token_endpoint'], [
            'grant_type' => 'authorization_code',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'redirect_uri' => $this->config['redirect'],
            'code' => request('code'),
        ])->json();

        $idToken = $tokenResponse['id_token'];

        $claims = app(OidcJwtValidator::class)->validate($idToken, $this->config);

        // @todo Validate nonce? or somewhere else? (see callback from the regular oauth2 flows)

        return new OidcUser($claims);
    }

    protected function discovery(): array
    {
        return Cache::remember(
            'sso:oidc:discovery:'.$this->config['issuer'],
            3600,
            fn () => Http::get(rtrim($this->config['issuer'], '/').'/.well-known/openid-configuration')->json(),
        );
    }
}
