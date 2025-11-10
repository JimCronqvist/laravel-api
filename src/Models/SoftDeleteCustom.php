<?php

namespace Cronqvist\Api\Models;

trait SoftDeleteCustom
{
    /**
     * Indicates if the model is currently forcing a hard delete.
     *
     * @var bool
     */
    protected $forceDeleting = false;

    /**
     * Boot the soft deleting trait for a model.
     *
     * @return void
     */
    public static function bootSoftDeleteCustom()
    {
        static::addGlobalScope(new SoftDeleteCustomScope);
    }

    /**
     * Perform the actual delete query on this model instance.
     *
     * @return void|null
     */
    protected function performDeleteOnModel()
    {
        if ($this->forceDeleting) {
            $this->setKeysForSaveQuery($this->newModelQuery())->delete();

            $this->exists = false;

            return;
        }

        $this->runSoftDelete();
    }

    /**
     * Perform the actual soft delete query on this model instance.
     *
     * @return void
     */
    protected function runSoftDelete()
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());

        $time = $this->freshTimestamp();

        $columns = [
            $this->getSoftDeleteColumn() => $this->getSoftDeleteDeletedValue(),
        ];

        $this->{$this->getSoftDeleteColumn()} = $this->getSoftDeleteDeletedValue();

        if ($this->syncsDeletedAt()) {
            $columns[$this->getDeletedAtColumn()] = $this->fromDateTime($time);
            $this->{$this->getDeletedAtColumn()} = $time;
        }

        if ($this->timestamps && ! is_null($this->getUpdatedAtColumn())) {
            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
            $this->{$this->getUpdatedAtColumn()} = $time;
        }

        $query->update($columns);

        $this->syncOriginalAttributes(array_keys($columns));

        $this->fireModelEvent('trashed', false);
    }

    /**
     * Register a "softDeleted" model event callback with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function softDeleted($callback)
    {
        static::registerModelEvent('trashed', $callback);
    }

    /**
     * Restore a soft-deleted model instance.
     *
     * @return bool|null
     */
    public function restore()
    {
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $this->setAttribute($this->getSoftDeleteColumn(), $this->getSoftDeleteActiveValue());

        if ($this->syncsDeletedAt()) {
            $this->setAttribute($this->getDeletedAtColumn(), null);
        }

        $this->exists = true;

        $result = $this->save();

        $this->fireModelEvent('restored', false);

        return $result;
    }

    /**
     * Register a "restored" model event callback with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function restored($callback)
    {
        static::registerModelEvent('restored', $callback);
    }

    /**
     * Force a hard delete on a soft-deleted model.
     *
     * @return bool|null
     */
    public function forceDelete()
    {
        if (! $this->exists) {
            return;
        }

        if ($this->fireModelEvent('forceDeleting') === false) {
            return false;
        }

        $this->forceDeleting = true;

        $deleted = $this->delete();

        $this->forceDeleting = false;

        if ($deleted) {
            $this->fireModelEvent('forceDeleted', false);
        }

        return $deleted;
    }

    /**
     * Determine if the model instance has been soft-deleted.
     *
     * @return bool
     */
    public function trashed()
    {
        return $this->getAttribute($this->getSoftDeleteColumn()) == $this->getSoftDeleteDeletedValue();
    }

    /**
     * Register a "forceDeleted" model event callback with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function forceDeleted($callback)
    {
        static::registerModelEvent('forceDeleted', $callback);
    }

    /**
     * Determine if the model is currently force deleting.
     *
     * @return bool
     */
    public function isForceDeleting()
    {
        return $this->forceDeleting;
    }

    /**
     * Determine if the model maintains a "deleted at" timestamp.
     *
     * @return bool
     */
    public function syncsDeletedAt(): bool
    {
        return property_exists($this, 'syncDeletedAt')
            ? (bool) $this->syncDeletedAt
            : false;
    }

    /**
     * Get the custom soft delete column name.
     *
     * @return string
     */
    public function getSoftDeleteColumn(): string
    {
        return property_exists($this, 'softDeleteColumn')
            ? $this->softDeleteColumn
            : 'deleted';
    }

    /**
     * Get the fully qualified custom soft delete column name.
     *
     * @return string
     */
    public function getQualifiedSoftDeleteColumn(): string
    {
        return $this->qualifyColumn($this->getSoftDeleteColumn());
    }

    /**
     * Get the name of the "deleted at" column.
     *
     * @return string
     */
    public function getDeletedAtColumn(): string
    {
        if (defined('static::DELETED_AT')) {
            return static::DELETED_AT;
        }

        return property_exists($this, 'deletedAtColumn')
            ? $this->deletedAtColumn
            : 'deleted_at';
    }

    /**
     * Get the fully qualified "deleted at" column.
     *
     * @return string
     */
    public function getQualifiedDeletedAtColumn(): string
    {
        return $this->qualifyColumn($this->getDeletedAtColumn());
    }

    /**
     * Get the "active" state value for the custom soft delete column.
     *
     * @return mixed
     */
    public function getSoftDeleteActiveValue()
    {
        return property_exists($this, 'softDeleteActiveValue')
            ? $this->softDeleteActiveValue
            : 0;
    }

    /**
     * Get the "deleted" state value for the custom soft delete column.
     *
     * @return mixed
     */
    public function getSoftDeleteDeletedValue()
    {
        return property_exists($this, 'softDeleteDeletedValue')
            ? $this->softDeleteDeletedValue
            : 1;
    }
}
