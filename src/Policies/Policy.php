<?php

namespace Cronqvist\Api\Policies;

use App\User;
use Cronqvist\Api\Exception\ApiException;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

abstract class Policy
{
    use HandlesAuthorization, IsAllowed;


    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\User|null  $user
     * @return bool
     * @throws ApiException
     */
    public function viewAny(?User $user)
    {
        if($this->isGuestsAllowed(__FUNCTION__)) return true;

        return $this->isAllowed($user, __FUNCTION__);
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\User|null  $user
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     * @throws ApiException
     */
    public function view(?User $user, Model $model)
    {
        if($this->isGuestsAllowed(__FUNCTION__)) return true;

        return $this->isAllowed($user, __FUNCTION__) || $this->isOwnAllowed($user, $model, __FUNCTION__);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\User|null  $user
     * @return bool
     * @throws ApiException
     */
    public function create(?User $user)
    {
        if($this->isGuestsAllowed(__FUNCTION__)) return true;

        return $this->isAllowed($user, __FUNCTION__);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\User|null  $user
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     * @throws ApiException
     */
    public function update(?User $user, Model $model)
    {
        if($this->isGuestsAllowed(__FUNCTION__)) return true;

        return $this->isAllowed($user, __FUNCTION__) || $this->isOwnAllowed($user, $model, __FUNCTION__);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\User|null  $user
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     * @throws ApiException
     */
    public function delete(?User $user, Model $model)
    {
        if($this->isGuestsAllowed(__FUNCTION__)) return true;

        return $this->isAllowed($user, __FUNCTION__) || $this->isOwnAllowed($user, $model, __FUNCTION__);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\User|null  $user
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     * @throws ApiException
     */
    public function restore(?User $user, Model $model)
    {
        if($this->isGuestsAllowed(__FUNCTION__)) return true;

        return $this->isAllowed($user, __FUNCTION__) || $this->isOwnAllowed($user, $model, __FUNCTION__);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\User|null  $user
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     * @throws ApiException
     */
    public function forceDelete(?User $user, Model $model)
    {
        if($this->isGuestsAllowed(__FUNCTION__)) return true;

        return $this->isAllowed($user, __FUNCTION__) || $this->isOwnAllowed($user, $model, __FUNCTION__);
    }

    /**
     * Determine whether the user can attach models to the relation.
     *
     * @param  \App\User|null  $user
     * @param  \Illuminate\Database\Eloquent\Model  $parentModel
     * @param  string  $relation
     * @param  \Illuminate\Database\Eloquent\Model  $childModel
     * @return bool
     * @throws ApiException
     */
    public function relationBelongsToManyAttach(?User $user, Model $parentModel, string $relation, Model $childModel)
    {
        if($this->isGuestsAllowed(__FUNCTION__)) return true;

        return $this->isAllowed($user, $relation.'.attach') && $this->isAllowedRaw($user, $childModel->getTable().'.view');
    }

    /**
     * Determine whether the user can detach models from the relation.
     *
     * @param \App\User|null $user
     * @param \Illuminate\Database\Eloquent\Model $parentModel
     * @param string $relation
     * @return bool
     * @throws ApiException
     */
    public function relationBelongsToManyDetach(?User $user, Model $parentModel, string $relation)
    {
        if($this->isGuestsAllowed(__FUNCTION__)) return true;

        return $this->isAllowed($user, $relation.'.detach');
    }

    /**
     * Determine whether the user can view multiple related models.
     *
     * @param \App\User|null $user
     * @param \Illuminate\Database\Eloquent\Model $parentModel
     * @param string $relation
     * @return bool
     * @throws ApiException
     */
    public function relationHasManyIndex(?User $user, Model $parentModel, string $relation)
    {
        if($this->isGuestsAllowed(__FUNCTION__)) return true;

        return $this->isAllowed($user, $relation.'.viewAny');
    }

    /**
     * Determine whether the user can view a specific related model.
     *
     * @param \App\User|null $user
     * @param \Illuminate\Database\Eloquent\Model $parentModel
     * @param string $relation
     * @param \Illuminate\Database\Eloquent\Model $childModel
     * @return bool
     * @throws ApiException
     */
    public function relationHasManyShow(?User $user, Model $parentModel, string $relation, Model $childModel)
    {
        if($this->isGuestsAllowed(__FUNCTION__)) return true;

        return $this->isAllowed($user, $relation.'.view');
    }

    /**
     * Determine whether the user can create a related model.
     *
     * @param \App\User|null $user
     * @param \Illuminate\Database\Eloquent\Model $parentModel
     * @param string $relation
     * @return bool
     * @throws ApiException
     */
    public function relationHasManyStore(?User $user, Model $parentModel, string $relation)
    {
        if($this->isGuestsAllowed(__FUNCTION__)) return true;

        return $this->isAllowed($user, $relation.'.store');
    }

    /**
     * Determine whether the user can update a specific related model.
     *
     * @param \App\User|null $user
     * @param \Illuminate\Database\Eloquent\Model $parentModel
     * @param string $relation
     * @param \Illuminate\Database\Eloquent\Model $childModel
     * @return bool
     * @throws ApiException
     */
    public function relationHasManyUpdate(?User $user, Model $parentModel, string $relation, Model $childModel)
    {
        return false;
    }

    /**
     * Determine whether the user can delete a specific related model.
     *
     * @param \App\User|null $user
     * @param \Illuminate\Database\Eloquent\Model $parentModel
     * @param string $relation
     * @param \Illuminate\Database\Eloquent\Model $childModel
     * @return bool
     * @throws ApiException
     */
    public function relationHasManyDestroy(?User $user, Model $parentModel, string $relation, Model $childModel)
    {
        return false;
    }

    /**
     * Determine whether the user can view the related model.
     *
     * @param \App\User|null $user
     * @param \Illuminate\Database\Eloquent\Model $parentModel
     * @param string $relation
     * @param \Illuminate\Database\Eloquent\Model $childModel
     * @return bool
     * @throws ApiException
     */
    public function relationHasOneShow(?User $user, Model $parentModel, string $relation, Model $childModel)
    {
        return false;
    }

    /**
     * Determine whether the user can upsert (create or update) the related model.
     *
     * @param \App\User|null $user
     * @param \Illuminate\Database\Eloquent\Model $parentModel
     * @param string $relation
     * @return bool
     * @throws ApiException
     */
    public function relationHasOneUpsert(?User $user, Model $parentModel, string $relation)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the related model.
     *
     * @param \App\User|null $user
     * @param \Illuminate\Database\Eloquent\Model $parentModel
     * @param string $relation
     * @param \Illuminate\Database\Eloquent\Model $childModel
     * @return bool
     * @throws ApiException
     */
    public function relationHasOneDestroy(?User $user, Model $parentModel, string $relation, Model $childModel)
    {
        return false;
    }

    /**
     * Determine whether the user can view the related model.
     *
     * @param \App\User|null $user
     * @param \Illuminate\Database\Eloquent\Model $parentModel
     * @param string $relation
     * @param \Illuminate\Database\Eloquent\Model $childModel
     * @return bool
     * @throws ApiException
     */
    public function relationBelongsToShow(?User $user, Model $parentModel, string $relation, Model $childModel)
    {
        return false;
    }

    /**
     * Determine whether the user can view multiple related models.
     *
     * @param \App\User|null $user
     * @param \Illuminate\Database\Eloquent\Model $parentModel
     * @param string $relation
     * @return bool
     * @throws ApiException
     */
    public function relationBelongsToManyIndex(?User $user, Model $parentModel, string $relation)
    {
        return false;
    }

    /**
     * Determine whether the user can view a specific related model.
     *
     * @param \App\User|null $user
     * @param \Illuminate\Database\Eloquent\Model $parentModel
     * @param string $relation
     * @param \Illuminate\Database\Eloquent\Model $childModel
     * @return bool
     * @throws ApiException
     */
    public function relationBelongsToManyShow(?User $user, Model $parentModel, string $relation, Model $childModel)
    {
        return false;
    }

    /**
     * Determine whether the user can sync related models.
     *
     * @param \App\User|null $user
     * @param \Illuminate\Database\Eloquent\Model $parentModel
     * @param string $relation
     * @return bool
     * @throws ApiException
     */
    public function relationBelongsToManySync(?User $user, Model $parentModel, string $relation)
    {
        return false;
    }

    /**
     * Determine whether the user can view or manipulate pivot attributes of the relation.
     *
     * @param \App\User|null $user
     * @param \Illuminate\Database\Eloquent\Model $parentModel
     * @param string $relation
     * @return bool
     * @throws ApiException
     */
    public function relationBelongsToManyPivot(?User $user, Model $parentModel, string $relation)
    {
        return false;
    }
}
