<?php

namespace Cronqvist\Api\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class ApiResource extends JsonResource
{
    use TransformRelationToResource;
    use SerializesResourceDatesAsZulu;

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // By default, we want to return the attributes of the model as an array. This is because we want to avoid
        // returning the relations of the model, which can be a security risk if not intentional.
        if($this->resource instanceof Model) {
            return $this->resource->attributesToArray();
        }

        return parent::toArray($request);
    }

    /**
     * Resolve the resource into an array.
     *
     * @param  \Illuminate\Http\Request|null  $request
     * @return array
     */
    public function resolve($request = null): array
    {
        $data = parent::resolve($request);

        $data = $this->transformRelations($data);
        $data = $this->serializeDates($data);

        return $data;
    }
}