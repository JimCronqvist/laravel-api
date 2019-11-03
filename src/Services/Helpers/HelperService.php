<?php

namespace Cronqvist\Api\Services\Helpers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;

class HelperService
{
    public static function getAllModelClasses()
    {
        $models = [];
        foreach(File::allFiles(app_path('Models')) as $file) {
            /** @var SplFileInfo $model */
            $model = str_replace(app_path(), '', $file->getPath()) . '/' . $file->getFilenameWithoutExtension();
            $model = App::getNamespace() . str_replace('/', '\\', trim($model, DIRECTORY_SEPARATOR));
            $models[] = $model;
        }
        return $models;
    }

    public static function getAllModelInstances()
    {
        $models = [];
        foreach(self::getAllModelClasses() as $modelClass) {
            $models[] = new $modelClass();
        }
        return $models;
    }
}