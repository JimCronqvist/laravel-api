<?php

namespace Cronqvist\Api\Services\MediaLibrary;

use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection as SpatieMediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaCollection extends SpatieMediaCollection
{
    public function jsonSerialize()
    {
        if (!($this->formFieldName ?? $this->collectionName)) {
            return [];
        }

        return old($this->formFieldName ?? $this->collectionName) ?? $this->map(function (Media $media) {
            return [
                'id' => $media->getKey(),
                'collection_name' => $media->collection_name,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'uuid' => $media->uuid,
                'preview_url' => $media->hasGeneratedConversion('preview') ? $media->getUrl('preview') : '',
                'original_url' => $media->getUrl(),
                'order' => $media->order_column,
                'custom_properties' => $media->custom_properties,
                'extension' => $media->extension,
                'size' => $media->size,
                'created_at' => $media->created_at,
                'updated_at' => $media->updated_at,
            ];
        })->keyBy('uuid');
    }
}