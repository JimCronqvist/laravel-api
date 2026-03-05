<?php

namespace Cronqvist\Api\Services\MediaLibrary;

use Cronqvist\Api\Http\Middleware\JsonMiddleware;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\File as FileObject;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaOnTheFly
{
    protected Media $media;

    protected bool $deleteAfterSend = true;

    protected array $operations = [];

    protected array $options = [];

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

    /**
     * Convert validated options into a normalized operations array.
     */
    protected function generateOperations()
    {
        $options = $this->getValidatedOptions();
        $operations = [];

        // Resize - fit/crop
        if(isset($options['width'], $options['height'])) {
            $crop = !empty($options['crop']);
            $operations['fit'] = [
                'method' => $crop ? 'crop' : 'max',
                'width' => (int) $options['width'],
                'height' => (int) $options['height'],
            ];

            unset($options['width'], $options['height'], $options['crop']);
        }

        // Other supported operations
        foreach($options as $option => $value) {
            $result = match ($option) {
                'optimize' => !empty($value) ? ['optimize' => true] : [],
                'quality'  => ['quality' => (int) $value],
                'format'   => ['format' => (string) $value],
                default    => [],
            };
            $operations = array_merge($operations, $result);
        }
        return $operations;
    }

    protected function getFilenameSuffix()
    {
        if(empty($this->operations)) return '';

        // Flatten operations into stable key/value pairs similar to the old suffix logic.
        $flat = [];

        if(isset($this->operations['fit'])) {
            $fit = $this->operations['fit'];
            $flat['fit'] = sprintf('%s-%d-%d', $fit['method'], $fit['width'], $fit['height']);
        }
        if(!empty($this->operations['optimize'])) {
            $flat['optimize'] = '1';
        }
        if(isset($this->operations['quality'])) {
            $flat['quality'] = (string) $this->operations['quality'];
        }
        if(isset($this->operations['format'])) {
            $flat['format'] = (string) $this->operations['format'];
        }
        if(empty($flat)) {
            return null;
        }
        ksort($flat);

        $string = '';
        foreach($flat as $key => $value) {
            $string .= substr($key, 0, 1);
            $string .= $value;
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
        // If requested, force output format by extension
        if(isset($this->operations['format']) && $this->operations['format'] === 'webp') {
            return 'webp';
        }

        return strtolower(pathinfo($this->media->file_name, PATHINFO_EXTENSION));
    }

    public function isImage()
    {
        return in_array($this->media->mime_type, [
            'image/gif',
            'image/jpeg',
            'image/png',
            'image/webp',
            //'image/svg+xml',
        ], true);
    }

    protected function ensureCacheDirExists()
    {
        $cacheDir = static::getCacheDir();
        if(!File::isDirectory($cacheDir)) {
            File::makeDirectory($cacheDir, 0755, true);
        }
    }

    protected function applyOperations(string $path)
    {
        $image = Image::load($path);

        // Resize (fit)
        if (isset($this->operations['fit'])) {
            $fit = $this->operations['fit'];

            $fitEnum = ($fit['method'] === 'crop') ? Fit::Crop : Fit::Max;
            $image->fit($fitEnum, $fit['width'], $fit['height']);
        }

        // Optimize (requires external tools via spatie/image-optimizer in many setups)
        if (!empty($this->operations['optimize']) && method_exists($image, 'optimize')) {
            $image->optimize();
        }

        // Quality (docs say it applies to JPEG; keep it best-effort)
        if (isset($this->operations['quality']) && method_exists($image, 'quality')) {
            $image->quality($this->operations['quality']);
        }

        // Format is handled by the output filename extension in spatie/image v3.
        $image->save($path);
    }

    protected function perform()
    {
        $this->operations = $this->generateOperations();

        $cacheDir = static::getCacheDir();
        $localOriginalFile = $this->media->getPath();

        if(!$this->isDiskDriverLocal()) {
            $extension = pathinfo($this->media->file_name, PATHINFO_EXTENSION);
            $localOriginalFile = $cacheDir . $this->media->uuid . '.' . strtolower($extension);
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

        // If no operations need to be done, or if it is not an image, return the original file.
        if(empty($this->operations) || !$this->isImage()) {
            if($this->isDiskDriverLocal()) {
                // Never delete the original file if that one is used directly
                $this->deleteAfterSend = false;
            }
            $file = new FileObject($localOriginalFile);
            return [$file, $this->getOutputFilename('', $this->getExtension())];
        }

        $suffix = $this->getFilenameSuffix();
        $extension = $this->getExtension();
        $transformFile = $cacheDir . $this->media->uuid . $suffix . '.' . $extension;

        if(!is_readable($transformFile) || File::size($transformFile) == 0) {
            if($this->isDiskDriverLocal()) {
                $this->ensureCacheDirExists();
            }

            // Start from original
            File::copy($localOriginalFile, $transformFile);

            // Apply transformations in-place (format determined by extension)
            $this->applyOperations($transformFile);
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