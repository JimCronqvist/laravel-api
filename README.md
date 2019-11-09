# Laravel API
Powerful automation of the creation and usage of a Laravel API

## Features

- Compose powerful REST APIs with ease, using pagination, filtering, sorting, eloquent relations, etc.
- Retrieve multiple resources in a single http request using the 'merge endpoint'.
- Automatic support for CRUD operations that directly supports authorization, input validation, with a Query Builder.
- Authentication (username/password) using Laravel Passport
- And much more!

This package is using a few powerful packages under the hood:
- spatie/laravel-query-builder
- spatie/laravel-permissions
- laravel/passport

## Installation

Run the following command to install the package using composer:

`composer require cronqvist/laravel-api`

Publish the assets provided by this package by using artisan:

`php artisan vendor:publish --tag=api`

Register the Middleware in 'app/Http/Kernel.php' accordingly:
```
protected $middlewareGroups = [
    ...
    'api' => [
        ...
        'api.guard', // Will ensure that the 'api' guard is used
        'api.json', // Will ensure that all responses are returned as json
    ]
];
```

To register any of the routes provided, update the boot method in 'app/Providers/AppServiceProvider' accordingly:
```
public function boot()
{
    // Enables the authentication routes    
    ApiServiceProvider::registerAuthRoutes(); 
    
    // Enables the API merge routes
    ApiServiceProvider::registerMergeRoutes(); 
}
```



## Usage
Create a complete API resource for a table with: 

`php artisan make:api Post`

The command will create all required files for maintaining a secure API resource:
- Controller
- Policy
- Resource
- FormRequest
- Model
- Migration

If you also want to have a Seeder and a Factory generated for you:

`php artisan make:api Post --seeder --factory`

### Controller

The API Controller generated will extend the "ApiController", which by default manage the standard CRUD operations, 
based on permissions. The API Controller will help with the following things:
- Automatically provided CRUD methods
- Automatic permission management for standard CRUD operations
- Automatic usage of the generated FormRequest class for validation of Create and Update requests
- Automatic authorization using the generated Policy
- Provide you with a standardized way of:
    - Filtering
    - Sorting
    - Pagination
    - Selecting Fields to minimize the response size
    - Include relations
- Retrieve multiple resources using one http requests, by merging multiple requests internally.
- All this done behind the scenes, leaving you with only minimal work to implement using a generated boilerplate.

#### Filtering

"RHS Colon" is used to get more control over filtering using query parameters.

`/api/posts?filter[column]=operator:value`

*TO BE WRITTEN AND IMPLEMENTED...*

#### Sorting, Selecting & Relations

This is done using "spatie/laravel-query-builder".
Please read their *documentation* for more details.

#### Retrieve multiple resources in one request

*TO BE WRITTEN...*

### Policy

Policies generated will allow you to easily manage permissions and control who will have access to perform any action.

The following permissions are used by default:
- {table}.viewAny
- {table}.view
- {table}.viewOwn
- {table}.create
- {table}.update
- {table}.updateOwn
- {table}.delete
- {table}.deleteOwn
- {table}.restore
- {table}.restoreOwn

To determine if a model is associated to the logged in user, you are able to specify the conditions freely using the 
`isOwn()` method that will be generated in your Policy class.
 
```
protected function isOwn(User $user, Post $post)
{
    return $post->user_id == $user->getKey();
}
```

You can easily specify if the endpoints allow guests for each action method:
```
protected $allowGuests = [
    'viewAny'     => false,
    'view'        => true, // Public API - no authentication required
    'create'      => false, 
    'update'      => false,
    'delete'      => false,
    'restore'     => false,
    'forceDelete' => false,
];
```

For other actions, you have a few helper methods available for you to help with authorization. To show this, lets look
at the default implementation of the view() method. 
```
public function view(?User $user, Model $model)
{
    if($this->isGuestsAllowed('view')) return true;

    return $this->isAllowed($user, 'view') || $this->isOwnAllowed($user, $model, 'view');
}
```

You can also define your own permissions, and check against them from inside your Policy:
```
$this->can($user, 'xyz'); // Will check if the user has a permission called "{table}.xyz".
```

### Resource

Resources generated will extend the "ApiResource", which will automatically detect loaded relations and map them to 
the related Resource classes. 
This is done to ensure consistent output for all models of the same class.
The mapping can be overridden by using the property called `$modelResourceMap`.

If you happen to override the default boilerplate and you no longer call parent::toArray(), you can still get this 
behavior by using the method 'whenLoadedToResource'.

```
public function toArray($request)
{
    return [
        'id' => $this->id,
        'author' => $this->whenLoadedToResource('author'),
    ];
}
```

This will automatically map the 'author' relation to the AuthorResource.

### Other generated classes

These uses standard Laravel boilerplate.

## Authentication

Laravel ships natively with an auth implementation when you are using it as a monolithic app. When using an API, you
are left to implement your own solution.

This package comes with a Authentication method that are easy to use, which depends on Laravel Passport.
This allows you to authenticate your users easily with a username and password.

Enable by registering the routes shown in the Configuration section above.

