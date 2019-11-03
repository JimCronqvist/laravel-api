<?php

namespace Cronqvist\Api\Policies;

use App\User;
use Cronqvist\Api\Exception\ApiException;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

class Policy
{
    use HandlesAuthorization;

    /**
     * Model class for the Policy
     *
     * @var string
     */
    protected $modelClass;

    /**
     * Control if guests are allowed for each endpoint
     *
     * @var array
     */
    protected $allowGuests = [];


    /**
     * Check if guests are allowed
     *
     * @param string|null $method
     * @return bool
     */
    protected function isGuestsAllowed($method = null)
    {
        $method = $method ?? $this->getCallingMethod();
        return isset($this->allowGuests[$method]) && $this->allowGuests[$method] === true;
    }

    /**
     * Check if the user is allowed to request a specific method
     *
     * @param User|null $user
     * @param string|null $ability
     * @return bool
     * @throws ApiException
     */
    protected function isAllowed(?User $user, $ability = null)
    {
        if($user === null) return false;

        $ability = $ability ?? $this->getCallingMethod();
        return $this->can($user, $ability);
    }

    /**
     * Check if the user is allowed to request a specific method, when the user is associated with the model
     *
     * @param User|null $user
     * @param Model $model
     * @param string|null $ability
     * @return bool
     * @throws ApiException
     */
    protected function isOwnAllowed(?User $user, Model $model, $ability = null)
    {
        if($user === null) return false;

        $ability = $ability ?? $this->getCallingMethod();
        return $this->can($user, $ability.'Own') && $this->isOwn($user, $model);
    }

    /**
     * Check if the user is authorized for the specific ability
     *
     * @param User $user
     * @param $ability
     * @return mixed
     * @throws ApiException
     */
    protected function can(User $user, $ability)
    {
        $ability = trim($this->getTable() . '.' . $ability, ' .:');
        return $user->can($ability);
    }

    /**
     * Get the table name of the policy model
     *
     * @return string
     * @throws ApiException
     */
    protected function getTable()
    {
        return $this->getModelInstance()->getTable();
    }

    /**
     * Get a new empty instance of the policy model
     *
     * @return \Illuminate\Database\Eloquent\Model
     * @throws ApiException
     */
    protected function getModelInstance()
    {
        if(empty($this->modelClass)) {
            throw new ApiException('No model class has been specified in the Policy');
        }
        return new $this->modelClass();
    }

    /**
     * Get the calling method name, only used as a fallback solution, avoid when possible.
     *
     * @return string
     */
    protected function getCallingMethod()
    {
        return debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
    }

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\User  $user
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
     * @param  \App\User  $user
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
     * @param  \App\User  $user
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
     * @param  \App\User  $user
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
     * @param  \App\User  $user
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
     * @param  \App\User  $user
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
     * @param  \App\User  $user
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     * @throws ApiException
     */
    public function forceDelete(?User $user, Model $model)
    {
        if($this->isGuestsAllowed(__FUNCTION__)) return true;

        return $this->isAllowed($user, __FUNCTION__) || $this->isOwnAllowed($user, $model, __FUNCTION__);
    }
}
