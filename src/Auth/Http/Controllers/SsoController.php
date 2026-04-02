<?php

namespace Cronqvist\Api\Auth\SSO\Http\Controllers;

use Cronqvist\Api\Auth\SSO\Models\SsoDomain;
use Cronqvist\Api\Auth\SSO\Rules\EmailOrDomain;
use Cronqvist\Api\Auth\SSO\Services\SsoCodeService;
use Cronqvist\Api\Auth\SSO\Services\OAuthTokenService;
use Cronqvist\Api\Auth\SSO\Services\SsoService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class SsoController extends Controller
{
    public function redirect(Request $request, string $provider)
    {
        return app(SsoService::class)
            ->redirect($request->query('email', $request->query('domain')), $provider);
    }

    public function callback(Request $request, string $provider)
    {
        $useExchangeFlow = config('sso.use_exchange_flow', false);

        return app(SsoService::class)
            ->setUseExchangeFlow($useExchangeFlow)
            ->callback($request->query('state', ''), $provider, $request);
    }

    public function exchange(Request $request)
    {
        $code = $request->validate([
            'code' => ['required', 'string'],
        ])['code'];

        $userId = app(SsoCodeService::class)
            ->consume($code, $request);

        $userModel = config('auth.providers.users.model');
        $user = $userModel::findOrFail($userId);

        return app(OAuthTokenService::class)->issueForUser($user);
    }

    public function providers(?string $emailOrDomain = null)
    {
        Validator::validate(
            ['emailOrDomain' => $emailOrDomain],
            ['emailOrDomain' => ['nullable', new EmailOrDomain()]],
        );

        $ssoService = app(SsoService::class);
        [$email, $domain] = $ssoService->splitEmailAndDomain($emailOrDomain);

        $ssoDomain = null;
        if($domain) {
            $ssoDomain = SsoDomain::query()
                ->where('domain', $domain)
                ->where('verified', 1)
                ->first();
        }

        $currentUserProvider = null;
        if($email) {
            $user = $ssoService->findUserByEmail($email);
            if($user) {
                $currentUserProvider = $user->getSsoProvider();
            }
        }

        // No domain → fallback to default providers
        if(!$ssoDomain) {
            $globalProviders = SsoService::getGlobalProviders();
            return response()->json([
                'providers' => $currentUserProvider
                    ? Arr::onlyValues($globalProviders, $currentUserProvider)
                    : $globalProviders,
                'mode' => SsoDomain::LOGIN_MODE_SSO_OPTIONAL,
            ]);
        }

        // If the user has a provider that is allowed for the domain, only return that one to avoid the wrong choice.
        $allowedProviders = $ssoDomain->allowed_providers;
        if($currentUserProvider && in_array($currentUserProvider, $allowedProviders)) {
            $allowedProviders = [$currentUserProvider];
        }

        if(empty($allowedProviders)) {
            return response()->json([
                'providers' => [],
                'mode' => $ssoDomain->login_mode,
                'error' => 'SSO not allowed. No providers configured for this ' . ($email ? 'email' : 'domain') . '.',
            ], 422);
        }

        return response()->json([
            'providers' => $allowedProviders,
            'mode' => $ssoDomain->login_mode,
        ]);
    }
}