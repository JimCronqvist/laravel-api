<?php

namespace Cronqvist\Api\Models;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

trait LogsActivityDefaults
{
    use LogsActivity;

    protected function defaultLogOptionsExceptions(array $extra = []): array
    {
        return array_merge($extra, [
            'password',
            'password_confirmation',
            'remember_token',
            'login_token',
            'api_token',
            'api_key',
            'access_token',
            'refresh_token',
            'client_secret',
            'secret',
            'private_key',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
    }

    protected function defaultLogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept($this->defaultLogOptionsExceptions())
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return $this->defaultLogOptions();
    }
}