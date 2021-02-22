<?php

namespace Cronqvist\Api\Http\Controllers;

use Cronqvist\Api\Exception\ApiAuthorizationException;
use Cronqvist\Api\Services\Auth\Utils;
use Cronqvist\Api\Services\Helpers\GuessForModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Spatie\QueryBuilder\QueryBuilderRequest;
use Exception;

abstract class ApiController extends BaseController
{
    use DispatchesJobs, ValidatesRequests;
    use AuthorizesRequests {
        authorize as protected baseAuthorize;
    }
    use GuessForModel;

    /**
     * Model for the API Resource Controller
     *
     * @var string
     */
    protected $modelClass;

    /**
     * Service class for the model
     *
     * @var object|null
     */
    protected $service;

    /**
     * Enable automatic authorization for the resource actions with the use of the model policy
     *
     * @var bool
     */
    protected $applyPolicy = true;

    /**
     * Paginate with X items per page.
     * 0 = display all and disable pagination
     *
     * @var bool
     */
    protected $perPage = 100;


    /**
     * Get the Builder instance used for the index and show actions.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     * @throws \Exception
     */
    protected function getBuilder()
    {
        return $this->getModelClass()::query();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Resources\Json\JsonResource
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Exception
     */
    protected function defaultIndex()
    {
        $this->authorizeMethod('index');
        $data = [];
        if(($builder = $this->getBuilder()) instanceof Builder) {
            $data = $this->perPage > 0
                ? $builder->paginate($this->perPage)
                : $builder->get();

            if($data instanceof AbstractPaginator) {
                $data = $data->setCollection($this->transformData($data->getCollection()));
            } else {
                $data = $this->transformData($data);
            }
        }
        $resource = $this->guessResourceClassFor($this->getModelClass());
        return $resource::collection($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Resources\Json\JsonResource
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Exception
     */
    protected function defaultStore()
    {
        $formRequest = $this->resolveFormRequestFor($this->getModelClass());
        $this->authorizeMethod('store');
        if($this->service && method_exists($this->service, 'create')) {
            $model = $this->service->create($formRequest->validated());
        } else {
            $model = $this->getModelClass()::create($formRequest->validated());
        }
        $resource = $this->guessResourceClassFor($this->getModelClass());
        return new $resource($model);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\JsonResource
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function defaultShow(int $id)
    {
        if(($builder = $this->getBuilder()) instanceof Builder) {
            // Remove unnecessary clauses from the queries that will only decrease performance
            $builder->setQuery($builder->getQuery()->cloneWithout([
                'joins', 'wheres', 'groups', 'havings', 'orders', 'offset',
                'unions', 'unionLimit', 'unionOffset', 'unionOrders'
            ])->cloneWithoutBindings(['join', 'where', 'groupBy', 'having', 'order', 'union', 'unionOrder']));

            // Empty the eager loads and add them after we are sure that we are authorized for this method.
            // Prevents issues if for example the auth object is used in relations, and avoids unnecessary queries if
            // not authorized.
            $eagerLoads = $builder->getEagerLoads();
            $builder->setEagerLoads([]);

            $model = $builder->findOrFail($id);
            Route::current()->setParameter(Route::current()->parameterNames()[0], $model);
        } else {
            $model = $this->getModelById($id);
        }

        $this->authorizeMethod('show', $model);

        // Now that we are authorized, eager load any relations that is supposed to be included
        if(!empty($eagerLoads)) {
            $builder->setEagerLoads($eagerLoads);
            $builder->eagerLoadRelations([$model]);
        }

        $model = $this->transformModel($model);
        $resource = $this->guessResourceClassFor($this->getModelClass());
        return new $resource($model);
    }

    /**
     * Get the model based on an ID
     *
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Model
     * @throws Exception
     */
    protected function getModelById(int $id)
    {
        $model = $this->getModelClass()::findOrFail($id);
        Route::current()->setParameter(Route::current()->parameterNames()[0], $model);
        return $model;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\JsonResource
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Exception
     */
    protected function defaultUpdate(int $id)
    {
        /** @var $model \Illuminate\Database\Eloquent\Model */
        $formRequest = $this->resolveFormRequestFor($this->getModelClass());
        $model = $this->getModelById($id);
        $this->authorizeMethod('update', $model);
        if($this->service && method_exists($this->service, 'update')) {
            $this->service->update($model, $formRequest->validated());
        } else {
            $model->update($formRequest->validated());
        }
        $resource = $this->guessResourceClassFor($this->getModelClass());
        return new $resource($model);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Exception
     */
    protected function defaultDestroy(int $id)
    {
        /** @var $model \Illuminate\Database\Eloquent\Model */
        $model = $this->getModelById($id);
        $this->authorizeMethod('destroy', $model);
        if($this->service && method_exists($this->service, 'delete')) {
            $this->service->delete($model);
        } else {
            $model->delete();
        }
        return response()->json(null, 204);
    }

    /**
     * To be overridden - if you need any transformation done before the data is passed on to the resource instance
     *
     * @param \Illuminate\Database\Eloquent\Collection $collection
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function transformData(Collection $collection) : Collection
    {
        return $collection;
    }

    /**
     * Transform the model by passing it to the transformData() method
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return Model
     */
    protected function transformModel(Model $model) : Model
    {
        $collection = new Collection([$model]);
        $collection = $this->transformData($collection);
        return $collection->first();
    }

    /**
     * Get the model resource class defined in the API Controller that extends this class
     *
     * @return string
     * @throws Exception
     */
    protected function getModelClass()
    {
        if(empty($this->modelClass)) {
            throw new Exception('No model resource has been specified in the API Controller');
        }
        return $this->modelClass;
    }

    /**
     * Authorize each controller method for the API Resource, as we are unable to use "$this->authorizeResource()"
     * because of our implementation where we do not resolve the model automatically in the route
     *
     * @param string $method
     * @param \Illuminate\Database\Eloquent\Model $model
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Exception
     */
    protected function authorizeMethod($method, Model $model = null)
    {
        if($this->applyPolicy) {
            $this->authorize($this->resourceAbilityMap()[$method] ?? $method, $model ?? $this->getModelClass());
        }
    }

    /**
     * Authorize a given action for the current user.
     *
     * @param mixed $ability
     * @param mixed|array $arguments
     * @return \Illuminate\Auth\Access\Response
     * @throws \Cronqvist\Api\Exception\ApiAuthorizationException
     */
    public function authorize($ability, $arguments = [])
    {
        try {
            return $this->baseAuthorize($ability, $arguments);
        } catch (AuthorizationException $exception) {
            if(auth('api')->user() === null) {
                // Check if the token has expired or is just invalid, to provide a better error message.
                $expired = false;
                try {
                    $jwt = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null; // Passport modifies the request instance, access the global directly
                    if(Str::startsWith($jwt, 'Bearer ')) {
                        $jwt = Str::substr($jwt, 7);
                    }
                    $token = Configuration::forUnsecuredSigner()->parser()->parse($jwt);
                    $validAt = new LooseValidAt(new SystemClock(new \DateTimeZone(\date_default_timezone_get())));
                    $expired = Configuration::forUnsecuredSigner()->validator()->validate($token, $validAt) === false;
                } catch (Exception $e) {}
                $message = $expired ? 'Not authenticated, token expired.' : 'Not authenticated, token invalid.';
                $exception = new AuthenticationException($message, ['api']);
            } else {
                $exception = new ApiAuthorizationException(null, null, $exception);
                $exception->setContext(auth('api')->user(), Route::currentRouteAction(), $ability, $arguments);
            }
            throw $exception;
        }
    }

    /**
     * Get the current Request instance, using the "spatie/laravel-query-builder" Request when available
     *
     * @return \Illuminate\Http\Request
     */
    protected function getRequest()
    {
        $request = request();
        return class_exists(QueryBuilderRequest::class)
            ? QueryBuilderRequest::fromRequest($request)
            : $request;
    }

    /**
     * Disable pagination by setting the perPage to 0.
     *
     * @return void
     */
    protected function disablePagination()
    {
        $this->perPage = 0;
    }

    /**
     * Handle calls to missing methods on the controller. Check if the default methods exist first.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        $defaultMethod = 'default'.ucfirst($method);
        if(method_exists($this, $defaultMethod)) {
            return $this->{$defaultMethod}(...$parameters);
        }
        return parent::__call($method, $parameters);
    }
}
