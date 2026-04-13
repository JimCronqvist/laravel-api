<?php

namespace Cronqvist\Api\Models;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity as SpatieLogsActivity;
use Spatie\Activitylog\Support\LogOptions;

trait LogsActivity
{
    use SpatieLogsActivity;

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

    protected function resolveModelForLogging(string $processingEvent): static
    {
        // Do not refresh the model from DB before logging, as it will create an extra unnecessary DB query.
        // This also helps with logging created models, where only the set values gets logged, instead of all attributes
        // after refreshing from DB.
        return $this;
    }

    public function activities(): MorphMany
    {
        return $this->activitiesAsSubject();
    }
}