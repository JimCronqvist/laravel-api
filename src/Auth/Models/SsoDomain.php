<?php

namespace Cronqvist\Api\Auth\SSO\Models;

use Illuminate\Database\Eloquent\Model;

class SsoDomain extends Model
{
    const LOGIN_MODE_SSO_OPTIONAL = 'sso_optional';
    const LOGIN_MODE_SSO_REQUIRED = 'sso_required';

    protected $table = 'sso_domains';

    protected $fillable = [
        'domain',
        'verified',
        'login_mode',
        'allowed_providers',
    ];

    protected $casts = [
        'allowed_providers' => 'array',
    ];

    public function customProviders()
    {
        return $this->belongsToMany(SsoCustomProvider::class, 'sso_custom_provider_domains', 'sso_domain_id', 'sso_custom_provider_id')
            ->where('sso_custom_providers.active', true);
    }
}