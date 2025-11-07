<?php

namespace Cronqvist\Api\Services\OpenApi;

use Cronqvist\Api\Exception\ApiException;
use Cronqvist\Api\Http\Controllers\ApiController;
use Cronqvist\Api\Services\Helpers\GuessForModel;
use Cronqvist\Api\Services\QueryBuilder\Filters\AbstractFilterRhs;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use Closure;
use phpDocumentor\Reflection\DocBlockFactory;
use Cronqvist\Api\Services\Helpers\AccessInstance;
use Cronqvist\Api\Services\QueryBuilder\QueryBuilder;

class GenerateDoc
{
    use GuessForModel;

    public $openapi = '3.0.0';

    protected $controllers = [];
    protected $reflections = [];

    protected $router;
    protected $request;
    protected $orgRequest;

    protected $actionMethods = [
        'index'   => 'GET',
        'show'    => 'GET',
        'store'   => 'POST',
        'update'  => 'PUT',
        'destroy' => 'DELETE',
    ];


    public function __construct(Router $router, Request $request)
    {
        $this->router = $router;
        $this->request = $request;
        $this->orgRequest = clone $request;

        $this->removeAfterResolvingCallbacks();
    }

    protected function removeAfterResolvingCallbacks()
    {
        AccessInstance::call(app(), function(){
            $this->afterResolvingCallbacks = []; // Removes 'ValidatesWhenResolved' from the Container
        });
    }

    protected function generateInfo()
    {
        return [
            'version' => '1.0.0',
            'title' => 'REST API Documentation',
            'description' => trim('
                This documentation is auto-generated according to the OpenAPI standard.
                For internal use only.
            '),
        ];
    }

    protected function generateSecurityDefinitions()
    {
        return [
            'components' => [
                'securitySchemes' => [
                    'AccessToken' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
            ],
        ];
    }

    protected function generatePaths()
    {
        $array = [];
        $routeList = $this->getRoutes();

        foreach($routeList as $route) {
            $class = Str::before($route['action'], '@');
            if(!in_array($class, $this->reflections)) {
                $this->controllers[$class] = app()->make($class);
                $this->reflections[$class] = new ReflectionClass($class);
            }
            $controller = $this->controllers[$class];
            $reflection = $this->reflections[$class];
            $docBlock   = $this->getDocBlock($route['action']);
            $uri        = $route['uri'];
            $prefix = preg_match('#^((?:.*/)?api/[^/]+)#', $uri, $m) ? $m[1] : '';

            foreach($route['method'] as $method) {
                if(in_array($method, ['HEAD', 'OPTIONS', 'PATCH'])) continue;

                $item = [
                    'summary' => $docBlock->getSummary(),
                    'description' => (string) $docBlock->getDescription(),
                    'tags' => [$reflection->getNamespaceName() . " - $prefix"],
                    'parameters' => $this->getRouteParameters($route),
                    'responses' => $this->getResponses($route),
                    'security' => $this->isRouteAllowingGuests($route) ? null : [['AccessToken' => []]],
                ];
                $array[$uri][strtolower($method)] = $item;
            }
        }
        //dd($array, $routeList);

        return $array;
    }

    protected function getResponses(array $route)
    {
        $action     = $this->splitAction($route['action'])['method'];
        $httpMethod = $this->getActionHttpMethod($route);
        $responses  = [];

        if(in_array($httpMethod, ['GET', 'POST', 'PUT'])) {
            $responses[200] = ['description' => 'OK'];
        }
        if(in_array($httpMethod, ['POST'])) {
            $responses[201] = ['description' => 'Entity created'];
            $responses[204] = ['description' => 'OK, no content returned'];
            $responses[409] = ['description' => 'Conflict, entity already exists'];
        }
        if(in_array($httpMethod, ['DELETE'])) {
            $responses[204] = ['description' => 'Entity deleted or detached'];
        }
        if(in_array($httpMethod, ['GET'])) {
            $responses[400] = ['description' => 'Provided data is invalid'];
        }
        if(in_array($httpMethod, ['GET', 'POST', 'PUT', 'DELETE']) && !$this->isRouteAllowingGuests($route)) {
            $responses[403] = ['description' => 'Not authorized'];
        }
        if(in_array($httpMethod, ['GET', 'PUT', 'DELETE']) && $action != 'index') {
            $responses[404] = ['description' => 'Model not found'];
        }
        if(in_array($httpMethod, ['POST', 'PUT'])) {
            $responses[422] = ['description' => 'Validation error'];
        }
        $responses[500] = ['description' => 'Internal Server Error'];

        return $responses;
    }

    protected function fakeMethod(string $method)
    {
        $this->request->setMethod($method);
    }

    protected function resetMethod()
    {
        $this->request->setMethod($this->orgRequest->getMethod());
    }

    protected function getRouteParameters(array $route)
    {
        $class = $this->splitAction($route['action'])['class'];
        $method = $this->splitAction($route['action'])['method'];
        $parameters = [];

        $this->fakeMethod($this->getActionHttpMethod($route));

        // Named parameters from the route
        foreach($route['parameters'] as $parameter) {
            $parameters[] = [
                'name' => $parameter,
                'in' => 'path',
                'description' => $parameter == 'id' ? 'ID of the resource' : Str::title($parameter),
                'required' => true,
                'schema' => [
                    'type' => $parameter == 'id' || Str::endsWith($parameter, 'Id') ? 'integer' : 'string'
                ],
            ];
        }

        // Query parameters from the query builder
        if($method == 'index' && ($controller = $this->controllers[$class]) instanceof ApiController) {
            $builder = AccessInstance::call($controller, function(){
                return $this->getBuilder();
            });
            if($builder instanceof QueryBuilder) {
                $parameters = array_merge($parameters, $this->getParametersFromQueryBuilder($builder, $controller));
            }
        }

        // Form fields from the FormRequest validation rules
        if(in_array($method, ['store', 'update']) && ($controller = $this->controllers[$class]) instanceof ApiController) {
            $formRequest = $this->resolveFormRequestFor($this->getModelClassFromRoute($route));
            $rules = $formRequest->rules();

            foreach($rules as $field => $rule) {
                $split = explode('|', $rule);
                $parameters[] = [
                    'name' => $field,
                    'in' => 'formData',
                    'description' => 'Rules: ' . $rule,
                    'required' => in_array('required', $split) && !in_array('sometimes', $split),
                    'schema' => [
                        'type' => in_array('integer', $split) ? 'integer' : (in_array('numeric', $split) ? 'number' : 'string')
                    ]
                ];
            }
        }

        $this->resetMethod();

        return $parameters;
    }

    protected function getActionHttpMethod(array $route)
    {
        $method = $this->splitAction($route['action'])['method'];
        return $this->actionMethods[$method] ?? 'GET';
    }

    protected function getParametersFromQueryBuilder(QueryBuilder $builder, ApiController $controller)
    {
        $sorts    = AccessInstance::getProperty($builder, 'allowedSorts');
        $includes = AccessInstance::getProperty($builder, 'allowedIncludes');
        $filters  = AccessInstance::getProperty($builder, 'allowedFilters');
        $parameters = [];

        // ?sort=
        if($sorts) {
            $sorts = $sorts->map(function($item) {
                return AccessInstance::getProperty($item, 'name');
            })->all();

            $parameters[] = [
                'name' => 'sort',
                'in' => 'query',
                'description' => 'Set sorting, prepend with a minus character for desc. Comma separate for multiple sorts. Allowed sorts: `' . implode('`, `', $sorts) . '`',
                'required' => false,
                'schema' => ['type' => 'string'],
            ];
        }

        // ?include=
        if($includes instanceof Collection && count($includes)) {
            $includes = $includes->map(function($item) {
                return AccessInstance::getProperty($item, 'name');
            })->all();

            $parameters[] = [
                'name' => 'include',
                'in' => 'query',
                'description' => 'Relations to be included. Comma separated for multiple includes. Allowed includes: `' . implode('`, `', $includes) . '`',
                'required' => false,
                'schema' => ['type' => 'string'],
            ];
        }

        // ?filter[x]=
        if($filters instanceof Collection && count($filters)) {
            $filters = $filters->map(function($item) {
                if(AccessInstance::call($item, function(){
                    return $this->filterClass instanceof AbstractFilterRhs;
                })) {
                    return [
                        'name'      => AccessInstance::getProperty($item, 'name'),
                        'operators' => AccessInstance::call($item, function(){
                            return AccessInstance::getProperty($this->filterClass, 'allowedOperators');
                        }),
                    ];
                }
                return null;
            })->filter()->all();

            foreach($filters as $filter) {
                $parameters[] = [
                    'name' => 'filter['.$filter['name'].']',
                    'in' => 'query',
                    'description' => 'Apply the filter with \'?filter['.$filter['name'].']={operator}:{value}\'. Allowed operators: `' . implode('`, `', $filter['operators']) . '`',
                    'required' => false,
                    'schema' => ['type' => 'string'],
                ];
            }
        }

        // ?limit=
        if($perPage = AccessInstance::getProperty($controller, 'perPage')) {
            $parameters[] = [
                'name' => 'limit',
                'in' => 'query',
                'description' => 'Lower the per page limit. You can never go higher than the default. Default: ' . $perPage,
                'required' => false,
                'schema' => ['type' => 'integer'],
            ];
        }

        // ?page=
        if($perPage) {
            $parameters[] = [
                'name' => 'page',
                'in' => 'query',
                'description' => 'Specify which page you want returned. Default: 1',
                'required' => false,
                'schema' => ['type' => 'integer'],
            ];
        }

        return $parameters;
    }

    protected function isRouteAllowingGuests(array $route)
    {
        $class = $this->splitAction($route['action'])['class'];
        $method = $this->splitAction($route['action'])['method'];
        $controller = $this->controllers[$class];
        $reflection = $this->reflections[$class];
        $modelClass = $this->getModelClassFromRoute($route);

        // A route using the ApiController
        if($modelClass && class_exists($this->guessPolicyClassFor($modelClass))) {
            $policy = $this->resolvePolicyFor($modelClass);
            $allowGuests = AccessInstance::getProperty($policy, 'allowGuests');
            $allowGuest = AccessInstance::call($controller, function() use($allowGuests, $method){
                $method = $this->resourceAbilityMap()[$method] ?? $method;
                return $allowGuests[$method] ?? false;
            });
            return $allowGuest;
        }

        // Check if the 'auth' middleware is assigned to the route
        if(in_array('auth', explode(',', $route['middleware']))) {
            return false;
        }

        return true;
    }

    protected function getModelClassFromRoute(array $route)
    {
        $controller = $this->controllers[$this->splitAction($route['action'])['class']];
        if($controller instanceof ApiController) {
            return (function(){
                return $this->getModelClass();
            })->call($controller);
        }
        return null;
    }

    protected function splitAction($action)
    {
        $split = explode('@', $action);
        return [
            'class' => $split[0],
            'method' => $split[1],
        ];
    }

    /**
     *
     *
     * @param string $action
     * @return \phpDocumentor\Reflection\DocBlock
     * @throws \ReflectionException
     * @throws ApiException
     */

    protected function getDocBlock($action)
    {
        [$class, $method] = explode('@', $action);
        $reflection = new ReflectionClass($class);

        if(!$reflection->hasMethod($method)) {
            $defaultMethods = [
                'index', 'show', 'store', 'update', 'destroy',
                'mediaIndex', 'mediaShow', 'mediaStore', 'mediaUpdate', 'mediaDestroy',
            ];
            if(in_array($method, $defaultMethods)) {
                $method = 'default' . ucfirst($method);
            }
        }

        if($reflection->hasMethod($method)) {
            $method = $reflection->getMethod($method);
        } else {
            throw new ApiException('Invalid route for: ' . $action);
        }

        $doc = $method->getDocComment();
        if(!$doc) {
            $doc = "Missing DocBlock for '" . $action . "'";
        }
        return DocBlockFactory::createInstance()->create($doc);
    }

    public function toArray()
    {
        $array = [];
        $array['openapi'] = $this->openapi;
        $array['servers'] = [['url' => request()->getSchemeAndHttpHost()]];
        $array['info'] = $this->generateInfo();
        $array['paths'] = $this->generatePaths();
        $array['definitions'] = new \stdClass();

        $array = $array + $this->generateSecurityDefinitions();

        return $array;
    }

    /**
     * Compile the routes into a displayable format.
     *
     * @return array
     */
    protected function getRoutes()
    {
        $routes = collect($this->router->getRoutes())->map(function ($route) {
            return $this->getRouteInformation($route);
        })->filter()->all();

        $routes = $this->sortRoutes('uri', $routes);

        return $routes;
    }

    /**
     * Get the route information for a given route.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return array
     */
    protected function getRouteInformation(Route $route)
    {
        $parameters = [];
        try {
            $parameters = $route->parameterNames();
        } catch (\LogicException $exception) {}

        if(
            count($parameters)
            && in_array($route->getActionMethod(), ['show', 'update', 'destroy'])
            && $route->getController() instanceof ApiController
        ) {
            $route->setUri(str_replace('{'.$parameters[0].'}', '{id}', $route->uri()));
            $parameters[0] = 'id';
        }

        return $this->filterRoute([
            'domain' => $route->domain(),
            'method' => $route->methods(),
            'uri'    => '/' . $route->uri(),
            'name'   => $route->getName(),
            'action' => ltrim($route->getActionName(), '\\'),
            'middleware' => $this->getMiddleware($route),
            'parameters' => $parameters,
        ]);
    }

    /**
     * Sort the routes by a given element.
     *
     * @param  string  $sort
     * @param  array  $routes
     * @return array
     */
    protected function sortRoutes($sort, array $routes)
    {
        return Arr::sort($routes, function ($route) use ($sort) {
            return $route[$sort];
        });
    }

    /**
     * Filter the routes by the 'api' middleware.
     *
     * @param  array  $route
     * @return array|null
     */
    protected function filterRoute(array $route)
    {
        return in_array('api', explode(',', $route['middleware'])) ? $route : null;
    }

    /**
     * Get before filters.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return string
     */
    protected function getMiddleware($route)
    {
        try {
            $middleware = $route->gatherMiddleware();
        } catch (BindingResolutionException $exception) {
            return '';
        }

        return collect($middleware)->map(function ($middleware) {
            return $middleware instanceof Closure ? 'Closure' : $middleware;
        })->implode(',');
    }
}
