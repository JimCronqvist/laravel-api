<?php

namespace Cronqvist\Api\Policies;

use App\User;
use Cronqvist\Api\Exception\ApiException;
use Illuminate\Database\Eloquent\Model;

trait IsAllowed
{
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
        return $this->can($user, $ability.'Own') && method_exists($this, 'isOwn') && $this->isOwn($user, $model);
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
}