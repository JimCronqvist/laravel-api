<?php

namespace Cronqvist\Api\Console\Commands;

use Cronqvist\Api\Services\Auth\AuthService;
use Illuminate\Console\Command;

class ApiCreatePersonalAccessToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:create_token {user_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a personal access token for the user with the given id';

    /**
     * Execute the console command.
     *
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function handle()
    {
        $userClass = config('auth.providers.users.model');
        $user = $userClass::findOrFail($this->argument('user_id'));
        $authService = app()->make(AuthService::class);
        $token = $authService->createPersonalAccessToken($user, 'Artisan Generated Token');
        $this->line($token);
    }
}
