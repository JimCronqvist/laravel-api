<?php

namespace Cronqvist\Api\Http\Resources;

use Cronqvist\Api\Services\Helpers\GuessForModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

trait TransformRelationToResource
{
    use GuessForModel;

    /**
     * Override the mapping of a Model with its Resource
     *
     * @var array
     */
    protected $modelResourceMap = [];

    /**
     * Transform all loaded relations with its own related resource class
     *
     * @param $data
     * @return mixed
     */
    protected function transformRelations($data)
    {
        $model = $this->resource;

        if(!$model instanceof Model) {
            return $data;
        }

        foreach($model->getRelations() as $relation => $value) {
            $key = $model::$snakeAttributes ? Str::snake($relation) : $relation;

            // Only transform relations that are part of the response
            if(!array_key_exists($key, $data)) continue;

            // Only transform relations that are not already transformed to a resource
            if($data[$key] instanceof JsonResource) {
                continue;
            }

            // Transform raw Model or Collection instances essentially coming from ->whenLoaded()
            if ($data[$key] instanceof Model || $data[$key] instanceof Collection) {
                $data[$key] = $this->transformRelation($data[$key]);
                continue;
            }

            // Transform relationships serialized by Eloquent.
            // NOTE: For now we have disabled this due to the performance impact of calling toArray() on large collections.
            // Current strategy is that we by default do not include relations in the toArray in the ApiResource.
            //if(is_array($data[$key]) && ($value instanceof Model || $value instanceof Collection) && $data[$key] === $value->toArray()) {
            //    $data[$key] = $this->transformRelation($value);
            //    continue;
            //}
        }

        return $data;
    }

    /**
     * Transform a relation with its own related resource class
     *
     * @param string $relation
     * @return mixed
     */
    protected function transformRelation($relation)
    {
        if(empty($relation)) return $relation;

        if($relation instanceof Model) {
            if($resource = $this->getResourceClassFor($relation)) {
                return new $resource($relation);
            }
        } else if($relation instanceof Collection) {
            if($relation->count() && $resource = $this->getResourceClassFor($relation->first())) {
                return $resource::collection($relation);
            }
        }
        return $relation;
    }

    /**
     * Get the resource class based on the model, if it exist.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return string
     */
    protected function getResourceClassFor(Model $model)
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
     * @return \Cronqvist\Api\Http\Resources\ApiResource|\Illuminate\Http\Resources\MissingValue
     */
    protected function whenLoadedToResource($relationship)
    {
        return $this->whenLoaded($relationship, function() use($relationship) {
            return $this->transformRelation($this->resource->getRelation($relationship));
        });
    }
}
