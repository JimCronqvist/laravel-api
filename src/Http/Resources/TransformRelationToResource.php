<?php

namespace Cronqvist\Api\Http\Resources;

use Cronqvist\Api\Services\Helpers\GuessForModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\QueryBuilderRequest;

trait TransformRelationToResource
{
    use GuessForModel;

    private ?ApiResource $parentResource = null;
    private ?string $requestedAs = null;

    /**
     * Override the mapping of a Model with its Resource
     *
     * @var array
     */
    protected array $modelResourceMap = [];

    /**
     * Transform all loaded relations with its own related resource class
     *
     * @param $data
     * @return mixed
     */
    protected function transformRelations($data)
    {
        $model = $this->resource instanceof Model ? $this->resource : null;
        if($model) {
            foreach($model->getRelations() as $relation => $value) {
                $class = get_class($model);
                $key = $class::$snakeAttributes ? Str::snake($relation) : $relation;
                $data[$key] = $this->transformRelation($value);
            }
        }
        return $data;
    }

    /**
     * Transform a relation with its own related resource class
     *
     * @param Model|Collection $relation
     * @return ApiResource|ResourceCollection|mixed
     */
    protected function transformRelation($relation)
    {
        if(empty($relation)) {
            return $relation;
        }

        if($relation instanceof Model) {
            if($resource = $this->getResourceClassFor($relation)) {
                return new $resource($relation);
            }
        } else if($relation instanceof Collection) {
            if($relation->count() && $resource = $this->getResourceClassFor($relation->first())) {
                /** @var ApiResource $resource */
                return $resource::collection($relation);
            }
        }
        return $relation;
    }

    /**
     * Get the resource class based on the model, if it exist.
     *
     * @param Model $model
     * @return string
     */
    protected function getResourceClassFor(Model $model): ?string
    {
        $modelClass = get_class($model);
        if(isset($this->modelResourceMap[$modelClass])) {
            return $this->modelResourceMap[$modelClass];
        }
        $resource = $this->guessResourceClassFor($modelClass);
        return class_exists($resource) ? $resource : null;
    }

    /**
     * Retrieve a relationship if it has been loaded and map it to its Resource class.
     *
     * @param string $relationship
     * @return ApiResource|ResourceCollection|MissingValue|mixed
     */
    protected function whenLoadedToResource(string $relationship)
    {
        return $this->whenLoaded($relationship, function() use($relationship) {
            $resource = $this->transformRelation($this->resource->getRelation($relationship));
            if($resource instanceof ApiResource) {
                $resource->setParent($this, $relationship);
            } else if($resource instanceof ResourceCollection) {
                $resource->each(fn(ApiResource $res) => $res->setParent($this, $relationship));
            }
            return $resource;
        });
    }

    public function setParent(ApiResource $parentResource, string $requestedAs):void {
        $this->parentResource = $parentResource;
        $this->requestedAs = $requestedAs;
    }

    protected function includePath(): array
    {
        $stack=[];
        $resource = $this;
        do {
            $stack[] = $resource->requestedAs;
        } while($resource = $resource->parentResource);
        array_pop($stack); //remove root resource
        return array_reverse($stack);
    }

    protected function globalIncludes(): \Illuminate\Support\Collection
    {
        return QueryBuilderRequest::createFrom(request())->includes();
    }

    protected function localIncludes(): array
    {
        $result = [];
        $path = $this->includePath();
        $pathLength = count($path);
        foreach ($this->globalIncludes() as $include){
            $include = explode('.',$include);
            for ($matches=0; $matches<$pathLength; $matches++) {
                $pathPart = $path[$matches];
                if($pathPart !== $include[$matches]){
                    break;
                }
            }
            $res = array_slice($include, $matches);
            if(count($res) && !in_array($res, $result, true)){
                $result[] = $res;
            }
        }
        return $result;
    }
    protected function directIncludes(): array
    {
        return array_unique(array_map(static fn($e)=>$e[0],$this->localIncludes()));
    }

    protected function whenIncluded($relationship, $callback)
    {
        if(in_array($relationship, $this->directIncludes(), true)) {
            return $callback();
        }
        return new MissingValue;
    }

    protected function whenIncludedToResource($relationship)
    {
        return $this->whenIncluded($relationship, function() use($relationship) {
            $resource = $this->transformRelation($this->resource->getRelation($relationship));
            if($resource instanceof ApiResource) {
                $resource->setParent($this, $relationship);
            } else if($resource instanceof ResourceCollection) {
                $resource->each(fn($res) => $res->setParent($this, $relationship));
            }
            return $resource;
        });
    }
}
