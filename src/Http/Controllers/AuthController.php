<?php

namespace Cronqvist\Api\Http\Controllers;

use Cronqvist\Api\Services\Auth\AuthService;
use App\Http\Controllers\Controller;

class AuthController extends Controller
{
    /**
     * UserResource
     *
     * @var string
     */
    protected $userResourceClass = 'App\Http\Resources\UserResource';


    /**
     * Login endpoint
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        return (new AuthService())->login(request('email'), request('password'));
    }

    /**
     * Refresh token endpoint
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return (new AuthService())->refresh(request()->cookie(AuthService::$refreshToken));
    }

    /**
     * Get the current logged in user
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|\App\Http\Resources\UserResource|null
     */
    public function user()
    {
        $user = (new AuthService())->user();
        if(class_exists($this->userResourceClass)) {
            $this->userResourceClass::withoutWrapping();
            return new $this->userResourceClass($user);
        }
        return $user;
    }

    /**
     * Logout endpoint
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Cronqvist\Api\Exception\ApiException
     */
    public function logout()
    {
        $auth = new AuthService();
        return $auth->logout($auth->user());
    }

    /**
     * Send reset password email
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResetLink()
    {
        return (new AuthService())->sendPasswordResetLink(request()->input('email'));
    }

    /**
     * Send reset password email
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function reset()
    {
        return (new AuthService())->reset(
            request()->input('email'),
            request()->input('token'),
            request()->input('password')
        );
    }
}
