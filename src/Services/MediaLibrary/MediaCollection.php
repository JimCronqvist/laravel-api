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
            $diskConfigured = DiskConfig::has($media->disk);
            //if(!$diskConfigured) {
            //    abort(500, "Media disk '{$media->disk}' is not configured");
            //}
            
            return [
                'id' => $media->getKey(),
                'collection_name' => $media->collection_name,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'uuid' => $media->uuid,
                'disk' => $media->disk,
                'preview_url' => $diskConfigured && $media->hasGeneratedConversion('preview') ? $media->getUrl('preview') : '',
                'original_url' => $diskConfigured ? $media->getUrl() : '',
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