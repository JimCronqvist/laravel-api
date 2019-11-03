<?php

namespace Cronqvist\Api\Http\Resources;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResource extends JsonResource
{
    /**
     * Override the mapping of a Model with its Resource
     *
     * @var array
     */
    protected $modelResourceMap = [];


    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->transformRelations(parent::toArray($request));
    }

    /**
     * Transform all loaded relations with its own related resource class
     *
     * @param $data
     * @return mixed
     */
    public function transformRelations($data)
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
    public function transformRelation($relation)
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
        $resource = str_replace('App\Models', 'App\Http\Resources', $modelClass) . 'Resource';
        return class_exists($resource) ? $resource : null;
    }
}