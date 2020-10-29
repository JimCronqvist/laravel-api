<?php

namespace Cronqvist\Api\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Spatie\MediaLibrary\InteractsWithMedia;

trait MediaEndpoints
{
    /**
     * Get a Media model by ID
     *
     * @return \Spatie\MediaLibrary\MediaCollections\Models\Media
     */
    protected function getMediaById(Model $model, int $id)
    {
        $media = $model->media()->findOrFail($id);
        Route::current()->setParameter('media', $media);
        return $media;
    }

    /**
     * Get the resource model by ID
     *
     * @param int $id
     * @return Model|InteractsWithMedia
     */
    protected function getModelById(int $id)
    {
        return parent::getModelById($id);
    }

    /**
     * Display a list of Media for the resource.
     *
     * @param int $modelId
     * @return \Illuminate\Support\Collection
     */
    protected function defaultMediaIndex(int $modelId)
    {
        $model = $this->getModelById($modelId);
        $this->authorizeMethod('show', $model);
        return $model->getMedia(request()->query('collection_name', 'default'));
    }

    /**
     * Display a single Media for the resource
     *
     * @param int $modelId
     * @param int $mediaId
     * @return string
     */
    protected function defaultMediaShow(int $modelId, int $mediaId)
    {
        $model = $this->getModelById($modelId);
        $media = $this->getMediaById($model, $mediaId);
        $this->authorizeMethod('show', $model);
        return $media;
    }

    /**
     * Store newly created Media in storage for the resource
     *
     * @param int $modelId
     * @return array
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig
     */
    protected function defaultMediaStore(int $modelId)
    {
        $model = $this->getModelById($modelId);
        $this->authorizeMethod('store', $model);

        $media = [];
        foreach($model->addAllMediaFromRequest() as $fileAdder) {
            $media[] = $fileAdder->toMediaCollection(request()->query('collection_name', 'default'));
        }
        return $media;
    }

    /**
     * Remove the specified Media from storage for the resource
     *
     * @param int $modelId
     * @param int $mediaId
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    protected function defaultMediaDestroy(int $modelId, int $mediaId)
    {
        $model = $this->getModelById($modelId);
        $media = $this->getMediaById($model, $mediaId);
        $this->authorizeMethod('destroy', $model);
        $media->delete();
        return response()->json(null, 204);
    }
}
