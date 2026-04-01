<?php

namespace Cronqvist\Api\Auth\SSO\Http\Middleware;

use Closure;
use Cronqvist\Api\Auth\SSO\Models\SsoDomain;
use Illuminate\Support\Str;

class EnforceSsoPolicyOnLogin
{
    public function handle($request, Closure $next)
    {
        $email = strtolower($request->input('email'));

        if(!$email) {
            return $next($request);
        }

        $domainPart = Str::after($email, '@');

        $domain = SsoDomain::query()->where('domain', $domainPart)->first();

        if(!$domain) {
            return $next($request);
        }

        $userModel = config('auth.providers.users.model');
        $user = $userModel::where('email', $email)->first();

        if($domain->login_mode === SsoDomain::LOGIN_MODE_SSO_REQUIRED && (!$user || !$user->sso_policy_bypass)) {
            return response()->json([
                'message' => 'This email domain requires SSO login.',
            ], 403);
        }

        return $next($request);
    }
}