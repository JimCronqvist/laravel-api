<?php

namespace Cronqvist\Api\Services\Helpers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

class HelperService
{
    /**
     * Get all model classes
     *
     * @return array
     */
    public static function getAllModelClasses()
    {
        $models = [];
        $user = new SplFileInfo(app_path() . '/User.php', '', '');
        foreach(array_merge([$user], File::allFiles(app_path('Models'))) as $file) {
            /** @var \SplFileInfo $model */
            $model = str_replace(app_path(), '', $file->getPath()) . '/' . $file->getFilenameWithoutExtension();
            $model = App::getNamespace() . str_replace('/', '\\', trim($model, DIRECTORY_SEPARATOR));
            $models[] = $model;
        }
        return array_unique($models);
    }

    /**
     * Get instances for all models
     *
     * @return array
     */
    public static function getAllModelInstances()
    {
        $models = [];
        foreach(self::getAllModelClasses() as $modelClass) {
            $models[] = new $modelClass();
        }
        return $models;
    }

    /**
     * Get all tables matching a filter, example to get all log tables "*_log"
     *
     * @param string $filter
     * @return array
     */
    public static function getAllTablesMatching($filter = '*')
    {
        $filter = $filter == '*' ? '' : str_replace('%', '', $filter);
        if(Str::endsWith($filter, '*')) {
            $filter = rtrim($filter, '*') . '%';
        }
        if(Str::startsWith($filter, '*')) {
            $filter = '%' . ltrim($filter, '*');
        }

        $filter = preg_replace("/[^\w\-%]+/", "", $filter);
        if(!empty($filter)) {
            $filter = ' LIKE "' . str_replace('_', '\_', $filter) . '"';
        }

        $query = 'SHOW TABLES' . $filter . ';';
        return array_map('reset', DB::select($query));
    }
}