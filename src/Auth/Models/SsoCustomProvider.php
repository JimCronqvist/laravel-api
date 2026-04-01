<?php

namespace Cronqvist\Api\Auth\SSO\Models;

use Illuminate\Database\Eloquent\Model;

class SsoCustomProvider extends Model
{
    protected $table = 'sso_custom_providers';

    protected $fillable = [
        'provider',
        'name',
        'client_id',
        'client_secret',
        'issuer',
        'tenant',
        'redirect_uri',
        'scopes',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'scopes' => 'array',
        'extra_config' => 'array',
    ];

    public function domains()
    {
        return $this->belongsToMany(SsoDomain::class, 'sso_custom_provider_domains', 'sso_custom_provider_id', 'sso_domain_id');
    }
}
