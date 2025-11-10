<?php

namespace Cronqvist\Api\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SoftDeleteCustomScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->where($model->getQualifiedSoftDeleteColumn(), '=', $model->getSoftDeleteActiveValue());
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    public function extend(Builder $builder)
    {
        $this->addWithTrashed($builder);
        $this->addOnlyTrashed($builder);
        $this->addWithoutTrashed($builder);
        $this->addRestore($builder);
        $this->addOnDelete($builder);
    }

    /**
     * Add the restore extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    protected function addRestore(Builder $builder)
    {
        $builder->macro('restore', function (Builder $builder) {
            $model = $builder->getModel();

            $updates = [
                $model->getSoftDeleteColumn() => $model->getSoftDeleteActiveValue(),
            ];

            if ($model->syncsDeletedAt()) {
                $updates[$model->getDeletedAtColumn()] = null;
            }

            return $builder
                ->withoutGlobalScope($this)
                ->update($updates);
        });
    }

    /**
     * Add the with-trashed extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    protected function addWithTrashed(Builder $builder)
    {
        $builder->macro('withTrashed', function (Builder $builder, $withTrashed = true) {
            return $withTrashed
                ? $builder->withoutGlobalScope($this)
                : $builder->withoutTrashed();
        });
    }

    /**
     * Add the without-trashed extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    protected function addWithoutTrashed(Builder $builder)
    {
        $builder->macro('withoutTrashed', function (Builder $builder) {
            $model = $builder->getModel();

            return $builder
                ->withoutGlobalScope($this)
                ->where(
                    $model->getQualifiedSoftDeleteColumn(),
                    '=',
                    $model->getSoftDeleteActiveValue()
                );
        });
    }

    /**
     * Add the only-trashed extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    protected function addOnlyTrashed(Builder $builder)
    {
        $builder->macro('onlyTrashed', function (Builder $builder) {
            $model = $builder->getModel();

            return $builder
                ->withoutGlobalScope($this)
                ->where($model->getQualifiedSoftDeleteColumn(), '=', $model->getSoftDeleteDeletedValue());
        });
    }

    /**
     * Add the onDelete handling to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    protected function addOnDelete(Builder $builder)
    {
        $builder->onDelete(function (Builder $builder) {
            $model = $builder->getModel();

            $updates = [
                $model->getSoftDeleteColumn() => $model->getSoftDeleteDeletedValue(),
            ];

            if ($model->syncsDeletedAt()) {
                $updates[$model->getDeletedAtColumn()] = $model->freshTimestamp();
            }

            return $builder
                ->withoutGlobalScope($this)
                ->update($updates);
        });
    }
}
