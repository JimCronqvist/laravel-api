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
     * Gets the current logged in user
     *
     * @return \App\User|\App\Http\Resources\UserResource
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
     */
    public function logout()
    {
        $auth = new AuthService();
        return $auth->logout($auth->user());
    }
}
