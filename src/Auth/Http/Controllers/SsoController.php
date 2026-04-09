<?php

namespace Cronqvist\Api\Auth\SSO\Http\Controllers;

use Cronqvist\Api\Auth\SSO\Services\SsoCodeService;
use Cronqvist\Api\Auth\SSO\Services\SsoService;
use Cronqvist\Api\Services\Auth\AuthService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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

        return app(AuthService::class)->loginAs($user);
    }

    public function providers(?string $emailOrDomain = null)
    {
        $providers = app(SsoService::class)->providers($emailOrDomain);

        return response()->json($providers, isset($providers['code']) ? (int) $providers['code'] : 200);
    }
}