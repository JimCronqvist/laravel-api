<?php

namespace Cronqvist\Api\Http\Resources;

use Cronqvist\Api\Services\Helpers\GuessForModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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
        $model = $this->resource instanceof Model ? $this->resource : null;
        if($model) {
            foreach($model->getRelations() as $relation => $value) {
                $data[$relation] = $this->transformRelation($value);
            }
        }
        return $data;
    }

    /**
     * Transform a relation with its own related resource class
     *
     * @param $relation
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
            if($resource = $this->getResourceClassFor($relation->first())) {
                return $resource::collection($relation);
            }
        }
        return $relation;
    }

    /**
     * Get the resource class based on the model, if it exist.
     *
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
