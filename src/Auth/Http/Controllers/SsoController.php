<?php

namespace Cronqvist\Api\Auth\SSO\Http\Controllers;

use Cronqvist\Api\Auth\SSO\Models\SsoDomain;
use Cronqvist\Api\Auth\SSO\Services\SsoCodeService;
use Cronqvist\Api\Auth\SSO\Services\OAuthTokenService;
use Cronqvist\Api\Auth\SSO\Services\SsoService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class SsoController extends Controller
{
    public function redirect(Request $request, string $provider)
    {
        return app(SsoService::class)
            ->redirect($request, $provider);
    }

    public function callback(Request $request, string $provider)
    {
        $useExchangeFlow = config('sso.use_exchange_flow', false);

        return app(SsoService::class)
            ->setUseExchangeFlow($useExchangeFlow)
            ->callback($request->query('state'), $provider, $request);
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

    public function providers(Request $request, string $domain = null)
    {
        $domain = $domain ?? '';
        if(Str::contains($domain, '@')) {
            $domain = Str::after($domain, '@');
        }

        $ssoDomain = SsoDomain::query()
            ->where('domain', $domain)
            ->where('verified', 1)
            ->first();

        // No domain → fallback to default providers
        if(!$ssoDomain) {
            $defaultProviders = array_keys(array_filter(config('services'), fn($service) => !empty($service['client_id'])));;
            return response()->json([
                'providers' => $defaultProviders,
                'mode' => SsoDomain::LOGIN_MODE_SSO_OPTIONAL,
            ]);
        }

        if(empty($ssoDomain->allowed_providers)) {
            return response()->json([
                'providers' => [],
                'mode' => $ssoDomain->login_mode,
                'error' => 'SSO not configured',
            ], 422);
        }

        return response()->json([
            'providers' => $ssoDomain->allowed_providers,
            'mode' => $ssoDomain->login_mode,
        ]);
    }
}