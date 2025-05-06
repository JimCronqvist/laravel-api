<?php

namespace Cronqvist\Api\Services\Filesystem;

class DiskConfig
{
    /**
     * Check if a disk config exist and appears to be configured
     *
     * @param $disk
     * @return bool
     */
    public static function has($disk): bool
    {
        $config = config('filesystems.disks.' . $disk);
        if($config === null) return false;

        if($config['driver'] == 's3') {
            if(empty($config['region']) || empty($config['bucket'])) {
                return false;
            }
        }

        return true;
    }
}