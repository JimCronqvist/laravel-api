<?php

namespace Cronqvist\Api\Auth\SSO\Services;

use Cronqvist\Api\Auth\SSO\Models\SsoDomain;
use Cronqvist\Api\Auth\SSO\Rules\EmailOrDomain;
use Cronqvist\Api\Services\Auth\AuthService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Cronqvist\Api\Auth\SSO\Adapters\SocialiteFactory as SsoSocialiteFactory;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class SsoService
{
    public static bool $useExchangeFlow = false;

    public static array $allowedGlobalProviders = []; // ['google', 'microsoft', ...]


    public function setUseExchangeFlow(bool $useExchangeFlow): static
    {
        static::$useExchangeFlow = $useExchangeFlow;
        return $this;
    }

    public function redirect(?string $emailOrDomain, string $provider)
    {
        $validator = Validator::make(
            ['emailOrDomain' => $emailOrDomain],
            ['emailOrDomain' => ['nullable', new EmailOrDomain()]],
        );
        if($validator->fails()) {
            abort(422, 'The provided email or domain is invalid for SSO.');
        }
        [$email, $domain] = $this->splitEmailAndDomain($emailOrDomain);

        $resolver = app(SsoProviderResolver::class);
        $config = $resolver->resolve($domain, $provider);
        $nonce = $this->shouldUseNonce($config) ? Str::random(32) : null;
        $state = app(SsoStateService::class)->encode([
            'provider' => $provider,
            'email' => $email,
            'domain' => $domain,
            'client_id' => $config['client_id'],
            'nonce' => $nonce,
        ]);

        $parameters = collect([
            'state'       => $state,
            'login_hint'  => $email, // For OIDC providers and some others, this can be used to pre-fill the username
            'hd'          => $provider === 'google' ? $domain : null, // Google specific parameter to restrict login to a specific domain, improves the UX.
            'domain_hint' => $provider === 'microsoft' ? $domain : null, // For Microsoft providers, this can be used to hint the domain for login, improving the UX.
            'nonce'       => $nonce,
            'prompt'      => 'select_account', // Force account selection on the provider side, to avoid silent logins with the wrong account.
        ])->whereNotNull()->all();

        $driver = app(SsoSocialiteFactory::class)
            ->driver($provider, $config, $email)
            ->stateless()
            ->with($parameters);

        return $driver->redirect();
    }

    public function callback(string $state, string $provider, Request $request)
    {
        if($request->query('error')) {
            // The provider returned an error, for example if the user canceled the login or if there was an error in the provider configuration.
            abort(403, "SSO provider '$provider' returned an error: " . $request->query('error_description', $request->query('error')));
        }

        $state = app(SsoStateService::class)->decode($state);

        $resolver = app(SsoProviderResolver::class);
        $config = $resolver->resolve($state['domain'], $provider);

        $flowValidator = app(SsoFlowValidator::class);
        $flowValidator->validateState($state, $provider, $config);

        $ssoProviderManager = app(SsoSocialiteFactory::class);
        $driver = $ssoProviderManager
            ->driver($provider, $config)
            ->stateless();

        try {
            $providerUser = $driver->user();
            [$providerEmail, $providerDomain] = $this->splitEmailAndDomain($providerUser->getEmail());
        } catch (\Exception $e) {
            if($e instanceof \GuzzleHttp\Exception\ClientException) {
                $response = $e->getResponse();
                if($response) {
                    $body = (string) $response->getBody(); // Useful for debugging, break here to see the full error.
                }
            }
            // One time use to get the user, if the page is being refreshed, replay attacks are prevented and usually cause the provider to give an error here.
            abort(403, 'Failed to retrieve user from the SSO provider ('.$provider.'). ' . (config('app.debug') ? ' '.$e->getMessage() : ''));
        }

        $flowValidator->validateEmail($providerEmail, $state);
        $flowValidator->validateDomain($providerDomain, $state);

        if($flowValidator->shouldValidateNonce($config)) {
            $idToken = $providerUser->accessTokenResponseBody['id_token'] ?? null;
            $flowValidator->validateNonce($idToken, $state, $config);
        }

        $user = $this->resolveUser($providerUser, $provider);
        if(!$user) {
            abort(404, 'No user found for this email address.');
        }

        if(static::$useExchangeFlow) {
            // For frontend SSO flows - preferred for SPAs and mobile apps
            // We redirect with a code that can be exchanged for a token, instead of issuing the token directly in the
            // callback response. This is to avoid issues with CORS and cookies in frontend applications.
            return $this->respondExchangeFlow($user, $request);
        } else {
            // For backend SSO flows - for traditional server-rendered apps, or when the SSO flow is initiated from the
            // backend and the frontend are just a consumer of the tokens.
            // We issue the token directly and redirect with the set-cookie headers set for the tokens.
            return $this->respondDirectly($user, $request);
        }
    }

    public function providers(?string $emailOrDomain)
    {
        Validator::validate(
            ['emailOrDomain' => $emailOrDomain],
            ['emailOrDomain' => ['nullable', new EmailOrDomain()]],
        );

        [$email, $domain] = $this->splitEmailAndDomain($emailOrDomain);

        $ssoDomain = null;
        if($domain) {
            $ssoDomain = SsoDomain::query()
                ->where('domain', $domain)
                ->where('verified', 1)
                ->first();
        }

        $currentUserProvider = null;
        $bypass = false;
        if($email) {
            $user = $this->findUserByEmail($email);
            if($user) {
                $currentUserProvider = $user->getSsoProvider();
                if($user->sso_policy_bypass) {
                    $bypass = true;
                }
            }
        }

        // No domain → fallback to default providers
        if(!$ssoDomain) {
            $globalProviders = $currentUserProvider
                ? Arr::onlyValues(static::getGlobalProviders(), $currentUserProvider)
                : static::getGlobalProviders();

            return [
                'mode' => SsoDomain::LOGIN_MODE_SSO_OPTIONAL,
                'providers' => collect($globalProviders)->map(fn($provider) => [
                    'provider' => $provider,
                    'name'     => ucfirst($provider),
                ])->all(),
            ];
        }

        // If the user has a provider that is allowed for the domain, only return that one to avoid the wrong choice.
        $allowedProviders = $ssoDomain->allowed_providers;
        if($currentUserProvider && in_array($currentUserProvider, $allowedProviders)) {
            $allowedProviders = [$currentUserProvider];
        }

        if(empty($allowedProviders)) {
            return [
                'mode' => $bypass ? SsoDomain::LOGIN_MODE_SSO_OPTIONAL : $ssoDomain->login_mode,
                'providers' => [],
                'description' => 'SSO not allowed. No providers configured for this ' . ($email ? 'email' : 'domain') . '.',
                'code' => 200,
            ];
        }

        $customProviders = $ssoDomain->customProviders ?? collect([]);
        $providers = [];
        foreach($allowedProviders as $provider) {
            $customForProvider = $customProviders->where('provider', $provider)->first();
            if($customForProvider) {
                $providers[] = [
                    'provider' => $provider,
                    'name' => $customForProvider->name,
                ];
            } else {
                $providers[] = [
                    'provider' => $provider,
                    'name' => ucfirst($provider),
                ];
            }
        }

        return [
            'mode' => $bypass ? SsoDomain::LOGIN_MODE_SSO_OPTIONAL : $ssoDomain->login_mode,
            'providers' => $providers,
        ];
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
        $authService = app(AuthService::class);
        $response = $authService->loginAs($user);

        // Turn the $response into a redirect instead, but keep the cookies intact
        $redirect = redirect($this->getFrontendUrl());
        foreach($response->headers->getCookies() as $cookie) {
            $redirect->headers->setCookie($cookie);
        }
        return $redirect;
    }

    public function findUserByEmail(string $email): ?Authenticatable
    {
        $userModel = config('auth.providers.users.model');

        return $userModel::query()
            ->where('email', $email)
            ->first();
    }

    protected function resolveUser(SocialiteUser $providerUser, string $provider): ?Authenticatable
    {
        $email = strtolower($providerUser->getEmail());
        $providerId = $providerUser->getId();

        $user = $this->findUserByEmail($email);
        if(!$user) {
            return null;
        }

        // If the user is not yet bound to an SSO provider, bind it. This allows existing users to be associated with
        // an SSO provider on their first login, without requiring a separate account linking flow.
        if(!$user->isSsoBound()) {
            $user->bindSso($provider, $providerId);
        }

        if(!$user->isSsoBound()) {
            abort(403, 'This user could not be associated to the SSO provider.');
        }

        // Identity enforcement
        if($user->sso_provider !== $provider) {
            abort(403, "This user is associated with a different SSO provider. Please use '$user->sso_provider' to log in.");
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

    public static function getGlobalProviders(): array
    {
        $providers = [];
        foreach(self::$allowedGlobalProviders as $provider) {
            if(config("services.$provider.client_id")) {
                $providers[] = $provider;
            }
        }
        return $providers;
    }

    public function splitEmailAndDomain(?string $emailOrDomain): array
    {
        $email = null;
        $domain = null;
        if(is_string($emailOrDomain)) {
            $emailOrDomain = strtolower($emailOrDomain);
            if(filter_var($emailOrDomain, FILTER_VALIDATE_EMAIL)) {
                $email = $emailOrDomain;
                $domain = explode('@', $emailOrDomain)[1] ?? null;
            } elseif (filter_var($emailOrDomain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                $domain = $emailOrDomain;
            }
        }
        return [$email, $domain];
    }
}
