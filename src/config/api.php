<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Namespaces
    |--------------------------------------------------------------------------
    |
    | These settings control the namespaces the application uses for the
    | different types of classes.
    |
    */

    'namespace_models'    => 'App\Models',
    'namespace_policies'  => 'App\Policies',
    'namespace_resources' => 'App\Http\Resources',
    'namespace_requests'  => 'App\Http\Requests',
    'namespace_services'  => 'App\Services\Api',

    /*
    |--------------------------------------------------------------------------
    | Stubs
    |--------------------------------------------------------------------------
    |
    | The stub file paths, only use this if you want to use your own custom
    | version of a specific stub when generating an API resource.
    |
    */

    //'stub_controller'         => app_path('stubs/controller.model.api.stub'),
    //'stub_controller_service' => app_path('stubs/controller.model.api.service.stub'),
    //'stub_policy'             => app_path('stubs/policy.stub'),
    //'stub_resource'           => app_path('stubs/resource.stub'),
    //'stub_request'            => app_path('stubs/request.stub'),
    //'stub_service'            => app_path('stubs/service.stub'),

    /*
    |--------------------------------------------------------------------------
    | Auth
    |--------------------------------------------------------------------------
    |
    | Configure the url for the forgot password process, the {token} placeholder
    | will automatically be replaced with the real token for the password reset.
    |
    */

    'forgot_password_request_uri' => '/auth/reset?email={email}&token={token}'
];