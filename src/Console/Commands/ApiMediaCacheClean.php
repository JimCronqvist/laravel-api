<?php

namespace Cronqvist\Api\Console\Commands;

use Cronqvist\Api\Services\MediaLibrary\MediaOnTheFly;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

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
        $path = MediaOnTheFly::getCacheDir();
        $files = Storage::allFiles($path);
        dd($files);
    }
}
