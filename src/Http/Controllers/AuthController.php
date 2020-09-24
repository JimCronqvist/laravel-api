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
     * Auth Service
     *
     * @var \Cronqvist\Api\Services\Auth\AuthService
     */
    protected $authService;


    /**
     * Constructor
     *
     * @param \Cronqvist\Api\Services\Auth\AuthService $authService
     */
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Login endpoint
     *
     * @return \Illuminate\Http\Response
     * @throws \Cronqvist\Api\Exception\ApiPassportException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function login()
    {
        return $this->authService->login(request('email'), request('password'));
    }

    /**
     * Refresh token endpoint
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function refresh()
    {
        $class = get_class($this->authService);
        $refreshTokenName = $class::$refreshToken;
        return $this->authService->refresh(request()->cookie($refreshTokenName));
    }

    /**
     * Get the current logged in user
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|\App\Http\Resources\UserResource|null
     */
    public function user()
    {
        $user = $this->authService->user();
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
        return $this->authService->logout($this->authService->user());
    }

    /**
     * Send reset password email
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Cronqvist\Api\Exception\ApiException
     */
    public function sendResetLink()
    {
        return $this->authService->sendPasswordResetLink(request()->input('email'));
    }

    /**
     * Send reset password email
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function reset()
    {
        return $this->authService->reset(
            request()->input('email'),
            request()->input('token'),
            request()->input('password')
        );
    }
}
