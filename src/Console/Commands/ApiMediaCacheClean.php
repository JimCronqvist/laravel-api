<?php

namespace Cronqvist\Api\Console\Commands;

use Cronqvist\Api\Services\MediaLibrary\MediaOnTheFly;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

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
        $dir = MediaOnTheFly::getCacheDir();

        // Output is one file per row, in the format: {bytes} {filename}
        $cmd = "find '$dir' -type f -printf '%T@ %s %p\n' | sort -zrn | awk '{ print $2,$3 }' | sed 's/ .*\// /g'";
        $process = Process::fromShellCommandline($cmd);
        $process->run();
        $output = $process->getOutput();

        $allowed = 1024 * 1024 * 1024 * (int) $this->argument('GB');
        $size = 0;
        $deleted = 0;

        foreach($this->getLines($output) as $line) {
            if(empty($line)) continue;

            list($bytes, $filename) = explode(' ', $line);
            $filepath = $dir . $filename;
            $size += $bytes;

            if($size > $allowed) {
                File::delete($filepath);
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
            "Total size: %s\nSize allowed: %s\nFiles deleted: %s\nMemory Usage: %s\n",
            $humanReadableBytes($size),
            $humanReadableBytes($allowed),
            $deleted,
            round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
        );
    }

    /**
     * Explode equivalent, but with a generator to avoid memory issues
     *
     * @param $data
     * @return \Generator
     */
    protected function getLines($data)
    {
        $start = 0;
        while(($end = strpos($data, PHP_EOL, $start)) !== false) {
            $line = trim(substr($data, $start, $end - $start));
            yield $line;
            $start = $end+1;
        }
    }
}
