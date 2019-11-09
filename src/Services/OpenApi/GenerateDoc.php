<?php

namespace Cronqvist\Api\Services\OpenApi;

use Cronqvist\Api\Exception\ApiException;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ReflectionClass;
use Closure;
use phpDocumentor\Reflection\DocBlockFactory;

class GenerateDoc
{
    public $openapi = '3.0.0';

    protected $controllers = [];
    protected $reflections = [];


    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    protected function generateInfo()
    {
        return [
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

            foreach($route['method'] as $method) {
                if(in_array($method, ['HEAD', 'OPTIONS'])) continue;
                //if($method == 'PATCH') continue; // Do we want to show this one?

                $protected = rand(0, 1);

                $item = [
                    'summary' => $docBlock->getSummary(),
                    'description' => $docBlock->getDescription(),
                    'operationId' => $route['name'],
                    'tags' => [$reflection->getNamespaceName()],
                    'consumes' => ['application/json'], // POST/PUT for API endpoints only, filter somehow..
                    'produces' => ['application/json'],
                    'parameters' => [

                    ],
                    'responses' => [
                        200 => ['description' => 'OK'],
                        404 => ['description' => 'Model not found'],
                    ],

                ];
                if($protected) {
                    $item['security'] = [['AccessToken' => []]];
                }
                $array[$route['uri']][strtolower($method)] = $item;
            }
        }
        //dd($array, $routeList);

        return $array;
    }

    /**
     *
     *
     * @param string $action
     * @return \phpDocumentor\Reflection\DocBlock
     * @throws \ReflectionException
     */

    protected function getDocBlock($action)
    {
        [$class, $method] = explode('@', $action);
        $reflection = new ReflectionClass($class);

        if(!$reflection->hasMethod($method)) {
            $defaultMethods = ['index', 'show', 'store', 'update', 'destroy'];
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
        $array['host'] = 'localhost';
        $array['schemas'] = ['https'];
        $array['basepath'] = '/api';
        $array['info'] = $this->generateInfo();
        $array['paths'] = $this->generatePaths();
        $array['definitions'] = [];

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
        return $this->filterRoute([
            'domain' => $route->domain(),
            'method' => $route->methods(),
            'uri'    => '/' . $route->uri(),
            'name'   => $route->getName(),
            'action' => ltrim($route->getActionName(), '\\'),
            'middleware' => $this->getMiddleware($route),
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
     * Filter the route by URI and / or name.
     *
     * @param  array  $route
     * @return array|null
     */
    protected function filterRoute(array $route)
    {
        return Str::startsWith($route['uri'], '/api/') ? $route : null;
    }

    /**
     * Get before filters.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return string
     */
    protected function getMiddleware($route)
    {
        return collect($route->gatherMiddleware())->map(function ($middleware) {
            return $middleware instanceof Closure ? 'Closure' : $middleware;
        })->implode(',');
    }
}
