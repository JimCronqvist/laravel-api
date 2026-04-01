<?php

namespace Cronqvist\Api\Auth\SSO\Services;

use Cronqvist\Api\Services\Auth\AuthService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Cronqvist\Api\Auth\SSO\Adapters\SocialiteFactory as SsoSocialiteFactory;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class SsoService
{
    protected $useExchangeFlow = false;


    public function setUseExchangeFlow(bool $useExchangeFlow)
    {
        $this->useExchangeFlow = $useExchangeFlow;
        return $this;
    }

    public function redirect(Request $request, string $provider)
    {
        $email = strtolower($request->validate([
            'email' => ['required', 'email'],
        ])['email']);

        $resolver = app(SsoProviderResolver::class);
        $config = $resolver->resolve($email, $provider);
        $nonce = $this->shouldUseNonce($config) ? Str::random(32) : null;
        $state = app(SsoStateService::class)->encode([
            'provider' => $provider,
            'email' => $email,
            'client_id' => $config['client_id'],
            'nonce' => $nonce,
        ]);

        $driver = app(SsoSocialiteFactory::class)
            ->driver($provider, $config, $email)
            ->stateless()
            ->with([
                'state' => $state,
                'login_hint' => $email, // For OIDC providers and some others, this can be used to pre-fill the username
            ]);

        if($nonce) {
            $driver->with(['nonce' => $nonce]);
        }

        return $driver->redirect();
    }

    public function callback(string $state, string $provider, Request $request)
    {
        $state = app(SsoStateService::class)->decode($state);

        $resolver = app(SsoProviderResolver::class);
        $config = $resolver->resolve($state['email'], $provider);

        $flowValidator = app(SsoFlowValidator::class);
        $flowValidator->validateState($state, $provider, $config);

        $ssoProviderManager = app(SsoSocialiteFactory::class);
        $driver = $ssoProviderManager
            ->driver($provider, $config)
            ->stateless();

        $providerUser = $driver->user();

        if($flowValidator->shouldValidateNonce($config)) {
            $idToken = $providerUser->accessTokenResponseBody['id_token'] ?? null;
            $flowValidator->validateNonce($idToken, $state, $config);
        }

        $user = $this->resolveUser($providerUser, $provider);
        if(!$user) {
            abort(404, 'No user found for this email address.');
        }

        if($this->useExchangeFlow) {
            // For frontend SSO flows - preferred for SPAs and mobile apps
            // We redirect with a code that can be exchanged for a token, instead of issuing the token directly in the
            // callback response. This is to avoid issues with CORS and cookies in frontend applications.
            return $this->respondExchangeFlow($user, $request);
        } else {
            // For backend SSO flows - for traditional server-rendered apps, or when the SSO flow is initiated from the
            // backend and the frontend are just a consumer of the tokens.
            // We issue the token directly and redirect with the set-cookie headers set for the tokens.
            $this->respondDirectly($user, $request);
        }
    }

    protected function getFrontendExchangeFlowUrl(): string
    {
        $frontendUrl = '/';
        $frontendExchangeFlowPath = config('sso.frontend_exchange_flow_path', '/auth/sso/callback');
        return $frontendUrl . $frontendExchangeFlowPath;
    }

    protected function getFrontendUrl(): string
    {
        return config('sso.frontend_path', '/');
    }

    protected function respondExchangeFlow(Authenticatable $user, Request $request): RedirectResponse
    {
        $code = app(SsoCodeService::class)->create($user, $request);
        $frontendExchangeFlowUrl = $this->getFrontendExchangeFlowUrl();
        return redirect($frontendExchangeFlowUrl . "?code=$code");
    }

    protected function respondDirectly(Authenticatable $user, Request $request): RedirectResponse
    {
        // @todo REPLACE THIS BY OAuthTokenService::class ?
        $authService = app(AuthService::class);
        $response = $authService->loginAs($user);

        // Turn the $response into a redirect instead, but keep the cookies intact
        $redirect = redirect($this->getFrontendUrl());
        foreach($response->headers->getCookies() as $cookie) {
            $redirect->headers->setCookie($cookie);
        }
        return $redirect;
    }

    protected function resolveUser(SocialiteUser $providerUser, string $provider): ?Authenticatable
    {
        $email = strtolower($providerUser->getEmail());
        $providerId = $providerUser->getId();

        $userModel = config('auth.providers.users.model');

        $user = $userModel::query()
            ->where('email', $email)
            ->first();

        if(!$user) {
            return null;
        }

        if(!$user->isSsoBound()) {
            $user->bindSso($provider, $providerId);
        }

        // Identity enforcement
        if($user->sso_provider !== $provider) {
            abort(403, 'This user is associated with a different SSO provider.');
        }
        if($user->sso_provider_id !== $providerId) {
            abort(403, 'This user is already associated with a different SSO identity.');
        }

        return $user;
    }

    protected function shouldUseNonce(array $config): bool
    {
        return isset($config['issuer']) || ($config['oidc'] ?? false);
    }
}
