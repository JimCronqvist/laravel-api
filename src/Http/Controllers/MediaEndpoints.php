<?php

namespace Cronqvist\Api\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\FileAdderFactory;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
     * Update the specified Media for the resource
     *
     * @param int $modelId
     * @param int $mediaId
     * @return string
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function defaultMediaUpdate(int $modelId, int $mediaId)
    {
        $model = $this->getModelById($modelId);
        $media = $this->getMediaById($model, $mediaId);
        $this->authorizeMethod('update', $model);

        $validated = Validator::make(request()->input(), [
            'collection_name' => 'sometimes|string',
            'file_name' => 'sometimes|string',
            'custom_properties' => 'array'
        ])->validated();

        if(isset($validated['collection_name']) && $media->collection_name != $validated['collection_name']) {
            $media = $media->move($model, $validated['collection_name'], $media->disk);
        }
        $media->fill(Arr::except($validated, ['collection_name']));
        $media->save();

        return $media->toArray();
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
        foreach($this->getFilesForUpload($model) as $collection => $fileAdders) {
            foreach($fileAdders as $fileAdder) {
                $fileAdder->sanitizingFileName(function($fileName) use($fileAdder) {
                    $fileName = $fileAdder->defaultSanitizer($fileName);
                    // Change the extension to be lowercase to tidy things up a bit
                    $extension = Str::lower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $fileName = trim(pathinfo($fileName, PATHINFO_FILENAME) . '.' . $extension, '.');

                    return $fileName;
                });
                $media[] = $fileAdder->toMediaCollection($collection);
            }
        }
        return is_array($media) && count($media) === 1 ? current($media)->toArray() : $media;
    }

    /**
     * Format the uploaded files to an array of 'folder => FileAdder[]'
     *
     * @param Model $model
     * @return \Spatie\MediaLibrary\MediaCollections\FileAdder[][]
     */
    protected function getFilesForUpload(Model $model)
    {
        $files = [];

        $numRootFiles = 0;
        foreach(request()->allFiles() as $file) {
            if(is_object($file)) $numRootFiles++;
        }

        foreach(request()->allFiles() as $k => $v) {
            // Example name: file.jpg
            if(is_object($v)) {
                $fileName = request()->query('file_name');
                if($fileName && !Str::contains($fileName, '.')) {
                    $extension = UploadedFile::createFromBase($v)->extension() ?? $v->getClientOriginalExtension();
                    $extension = str_replace('jpeg', 'jpg', Str::lower($extension));
                    $fileName .= $extension ? '.' . $extension : '';
                }

                $files[request()->query('collection_name', 'default')][] = $numRootFiles === 1 && $fileName
                    ? FileAdderFactory::create($model, $v)->setFileName($fileName)
                    : FileAdderFactory::create($model, $v);
            } else if(is_array($v)) {
                foreach($v as $k2 => $v2) {
                    // Example name: file[file.jpg]
                    if(is_object($v2)) {
                        $files[request()->query('collection_name', 'default')][] = Str::contains($k2, '.')
                            ? FileAdderFactory::create($model, $v2)->setFileName($k2)
                            : FileAdderFactory::create($model, $v2);
                    } else if(is_array($v2)) {
                        foreach($v2 as $k3 => $v3) {
                            // Example name: file[folder][file.jpg]
                            if(is_object($v3)) {
                                $files[$k2][] = Str::contains($k3, '.')
                                    ? FileAdderFactory::create($model, $v3)->setFileName($k3)
                                    : FileAdderFactory::create($model, $v3);
                            } else {
                                throw new BadRequestHttpException('Malformed request. Files are too deeply nested');
                            }
                        }
                    }
                }
            }
        }
        return $files;
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
