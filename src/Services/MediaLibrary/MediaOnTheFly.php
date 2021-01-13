<?php

namespace Cronqvist\Api\Services\MediaLibrary;

use Cronqvist\Api\Http\Middleware\JsonMiddleware;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\File as FileObject;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Image\Manipulations;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\ImageFactory;

class MediaOnTheFly
{
    protected $media;
    protected $deleteAfterSend = true;
    protected $manipulations;
    protected $options = [];

    public function __construct(Media $media)
    {
        $this->media = $media;
    }

    public function setOptions(array $options)
    {
        $this->options = $options;
        return $this;
    }

    protected function rules()
    {
        return [
            'width' => 'int|min:1|required_if:crop,1',
            'height' => 'int|min:1|required_if:crop,1',
            'optimize' => 'bool|in:1',
            'format' => 'in:webp',
            'quality' => 'int|between:1,100',
            'crop' => 'bool|in:1',
        ];
    }

    protected function getValidatedOptions()
    {
        $query = $this->options + request()->query();
        try {
            $validated = Validator::make($query, $this->rules())->validate();
        } catch (ValidationException $e) {
            // Ensure JSON
            (new JsonMiddleware())->handle(request(), fn() => 1);
            throw $e;
        }
        return $validated;
    }

    protected function generateManipulations()
    {
        $options = $this->getValidatedOptions();

        $manipulations = new Manipulations();
        if(isset($options['width'], $options['height'])) {
            $fit = empty($options['crop']) ? Manipulations::FIT_MAX : Manipulations::FIT_CROP;
            $manipulations->fit($fit, $options['width'], $options['height']);
            unset($options['width'], $options['height'], $options['crop']);
        }
        foreach($options as $option => $value) {
            if(array_key_exists($option, $options)) {
                if(in_array($option, ['optimize'])) {
                    $manipulations->{$option}();
                } else {
                    $manipulations->{$option}($value);
                }
            }
        }
        return $manipulations;
    }

    protected function getFilenameSuffix()
    {
        $options = current($this->manipulations->toArray());
        if(!$options) return null;
        ksort($options);

        $string = '';
        foreach($options as $key => $value) {
            $string .= substr($key, 0, 1);
            $string .= strpos($value, '[') === false ? trim($value) : '';
            $string .= '_';
        }
        return '__' . trim($string, '_');
    }

    public static function getCacheDir()
    {
        $cacheDir = config('media-library.cache_directory_path') ?? storage_path('media-library/cache');
        return '/' . trim($cacheDir, '/') . '/';
    }

    protected function getOutputFilename($suffix, $extension)
    {
        return pathinfo($this->media->file_name, PATHINFO_FILENAME) . $suffix . '.' . $extension;
    }

    protected function isDiskDriverLocal()
    {
        return $this->media->getDiskDriverName() == 'local';
    }

    protected function getExtension()
    {
        return $this->manipulations->hasManipulation('format')
            ? $this->manipulations->getFirstManipulationArgument('format')
            : strtolower($this->media->getExtensionAttribute());
    }

    protected function ensureCacheDirExists()
    {
        $cacheDir = static::getCacheDir();
        if(!File::isDirectory($cacheDir)) {
            File::makeDirectory($cacheDir, 0755, true);
        }
    }

    protected function perform()
    {
        $this->manipulations = $this->generateManipulations();

        $cacheDir = static::getCacheDir();
        $localOriginalFile = $this->media->getPath();

        if(!$this->isDiskDriverLocal()) {
            $localOriginalFile = $cacheDir . $this->media->uuid . '.' . strtolower($this->media->getExtensionAttribute());
            if(!is_readable($localOriginalFile) || File::size($localOriginalFile) == 0) {
                $this->ensureCacheDirExists();
                try {
                    app(Filesystem::class)->copyFromMediaLibrary($this->media, $localOriginalFile);
                } catch (FileNotFoundException $e) {
                    throw new FileNotFoundException('MediaOnTheFly: ' . $e->getMessage(), $e->getCode(), $e);
                }
                if(File::size($localOriginalFile) == 0) {
                    File::delete($localOriginalFile);
                    throw new \Exception('Unable to retrieve the file (size=0) for media ID: ' . $this->media->id);
                }
            }
        }

        if($this->manipulations->isEmpty()) {
            if($this->isDiskDriverLocal()) {
                // Never delete the original file if that one is used directly
                $this->deleteAfterSend = false;
            }
            $file = new FileObject($localOriginalFile);
            return [$file, $file->getFilename()];
        }

        $suffix = $this->getFilenameSuffix();
        $extension = $this->getExtension();
        $transformFile = $cacheDir . $this->media->uuid . $suffix . '.' . $extension;

        if(!is_readable($transformFile) || File::size($transformFile) == 0) {
            if($this->isDiskDriverLocal()) {
                $this->ensureCacheDirExists();
            }

            File::copy($localOriginalFile, $transformFile);

            ImageFactory::load($transformFile)
                ->manipulate($this->manipulations)
                ->save();
        }

        return [
            new FileObject($transformFile),
            $this->getOutputFilename($suffix, $extension)
        ];
    }

    public function cache()
    {
        $this->deleteAfterSend = false;
        return $this;
    }

    public function output()
    {
        [$file, $filename] = $this->perform();
        return response()
            ->download($file, $filename, [], 'inline')
            ->deleteFileAfterSend($this->deleteAfterSend);
    }

    public function download()
    {
        [$file, $filename] = $this->perform();
        return response()
            ->download($file, $filename)
            ->deleteFileAfterSend($this->deleteAfterSend);
    }
}
