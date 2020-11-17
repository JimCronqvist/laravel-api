<?php

namespace Cronqvist\Api\Console\Commands;

use Cronqvist\Api\Services\MediaLibrary\MediaOnTheFly;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Finder;

class ApiMediaCacheClean extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'api:media-cache-clean';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:media-cache-clean {GB}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete the oldest files above the specified limit in the media on the fly cache folder.';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $directory = MediaOnTheFly::getCacheDir();
        $files = iterator_to_array(
            Finder::create()
                ->files()
                ->ignoreDotFiles(false)
                ->in($directory)
                ->sortByModifiedTime()
                ->reverseSorting()
            , false
        );
        $allowed = 1024 * 1024 * 1024 * (int) $this->argument('GB');
        $size = 0;
        $deleted = 0;
        foreach($files as $file) {
            /** @var $file \Symfony\Component\Finder\SplFileInfo */
            $size += $file->getSize();

            if($size > $allowed) {
                File::delete($file->getPathname());
                $deleted++;
            }
        }

        $humanReadableBytes = function($bytes) {
            if($bytes == 0) return '0.00 B';
            $s = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
            $e = floor(log($bytes, 1024));
            return round($bytes/pow(1024, $e), 2) . ' ' . $s[$e];
        };

        return printf(
            "Total size: %s\nSize allowed: %s\nFiles deleted: %s\n",
            $humanReadableBytes($size),
            $humanReadableBytes($allowed),
            $deleted
        );
    }
}
