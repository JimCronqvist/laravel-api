<?php

namespace Cronqvist\Api\Exception;

use Cronqvist\Api\Services\Helpers\GuessForModel;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\App;

class ApiAuthorizationException extends AuthorizationException
{
    use GuessForModel;

    public function setContext($user, $action, $ability, $model)
    {
        $message = $this->getMessage();
        if(config('app.debug')) {
            $message .= "\n- " . ($user ? 'Authenticated (UserID: ' . $user->getKey() . ')' : 'Not authenticated');
            $message .= "\n- " . $action;
            $model = is_object($model) ? get_class($model) : $model;
            $message .= "\n- " . $this->guessPolicyClassFor($model) . '@' . $ability;
        }
        $this->message = $message;
    }
}
