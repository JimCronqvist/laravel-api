<?php

namespace Cronqvist\Api\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Routing\Controller as BaseController;
use Exception;
use Spatie\QueryBuilder\QueryBuilderRequest;

class ApiController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Model for the API Resource Controller
     *
     * @var string
     */
    protected $modelClass;

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
     */
    public function index()
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
        $resource = $this->getResourceClass();
        return $resource::collection($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Resources\Json\JsonResource
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store()
    {
        $formRequest = $this->getFormRequest();
        $this->authorizeMethod('store');
        $model = $this->getModelClass()::create($formRequest->validated());
        $resource = $this->getResourceClass();
        return new $resource($model);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\JsonResource
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show(int $id)
    {
        if(($builder = $this->getBuilder()) instanceof Builder) {
            // Remove unnecessary clauses from the queries that will only decrease performance
            $builder->setQuery($builder->getQuery()->cloneWithout([
                'joins', 'wheres', 'groups', 'havings', 'orders', 'offset',
                'unions', 'unionLimit', 'unionOffset', 'unionOrders'
            ]));
            $model = $builder->findOrFail($id);
        } else {
            $model = $this->getModelClass()::findOrFail($id);
        }
        $this->authorizeMethod('show', $model);
        $model = $this->transformModel($model);
        $resource = $this->getResourceClass();
        return new $resource($model);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\JsonResource
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(int $id)
    {
        $formRequest = $this->getFormRequest();
        $model = $this->getModelClass()::findOrFail($id);
        $this->authorizeMethod('update', $model);
        $model->update($formRequest->validated());
        $resource = $this->getResourceClass();
        return new $resource($model);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function destroy(int $id)
    {
        $model = $this->getModelClass()::findOrFail($id);
        $this->authorizeMethod('destroy', $model);
        $model->delete();
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
     * Get the resource class based on the model
     *
     * @return string
     * @throws \Exception
     */
    protected function getFormRequestClass()
    {
        return str_replace('App\Models', 'App\Http\Requests', $this->getModelClass()) . 'Request';
    }

    /**
     * Get the resource class based on the model
     *
     * @return string
     * @throws \Exception
     */
    protected function getResourceClass()
    {
        return str_replace('App\Models', 'App\Http\Resources', $this->getModelClass()) . 'Resource';
    }

    /**
     * Authorize each controller method for the API Resource, as we are unable to use "$this->authorizeResource()"
     * because of our implementation where we do not resolve the model automatically in the route
     *
     * @param string $method
     * @param \Illuminate\Database\Eloquent\Model $model
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function authorizeMethod($method, Model $model = null)
    {
        if($this->applyPolicy) {
            $this->authorize($this->resourceAbilityMap()[$method] ?? $method, $model ?? $this->getModelClass());
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
     * Get the FormRequest instance for the resource
     *
     * @return \Illuminate\Foundation\Http\FormRequest
     * @throws \Exception
     */
    protected function getFormRequest()
    {
        return app($this->getFormRequestClass());
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
}
