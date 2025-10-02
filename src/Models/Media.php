<?php

namespace MetaFramework\Mediaclass\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use MetaFramework\Mediaclass\Config;
use MetaFramework\Mediaclass\Path;
use MetaFramework\Mediaclass\Traits\Accessors;
use Symfony\Component\Mime\MimeTypes;

/**
 * @property string                                                   $filename
 * @property string                                                   $position
 * @property array                                                    $description
 * @property string                                                   $mime
 * @property int                                                      $id
 * @property string                                                   $group
 * @property \MetaFramework\Mediaclass\Interfaces\MediaclassInterface $model
 */
class Media extends Model
{
    use Accessors;

    protected $table = 'mediaclass';

    protected $guarded = [];
    protected $casts
        = [
            'description' => 'array',
            'storable' => 'array',
        ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function extension(): ?string
    {
        return MimeTypes::getDefault()->getExtensions($this->mime)[0] ?? null;
    }

    public function sizeable(): bool
    {
        return match ($this->mime) {
            'image/png', 'image/jpeg' => true,
            default => false,
        };
    }

    /**
     * @param  string       $size
     * @param  string|null  $cropKey
     *
     * @return string
     */
    public function url(string $size = 'sm', ?string $cropKey = null): string
    {
        // If a crop key is provided and exists, return the cropped URL
        if ($cropKey && $this->isCroppedForKey($cropKey)) {
            return $this->getCroppedUrl($cropKey);
        }

        if ($size === 'cropped' && $this->isCropped()) {
            // Return the first available crop
            $crops = $this->getCroppedImages();
            if (!empty($crops)) {
                $firstKey = array_key_first($crops);
                return $this->getCroppedUrl($firstKey);
            }
        }

        return Storage::disk('media')->url(
            Path::mediaFolderForMedia($this).'/'.$this->dimensionPrefix(prefix: $size).$this->filename.'.'.$this->extension(),
        );
    }


    public function file(string $size = 'sm'): string
    {
        return Storage::disk('media')->get(Path::mediaFolderForMedia($this).'/'.$this->dimensionPrefix(prefix: $size).$this->filename.'.'.$this->extension());
    }


    /**
     * @param  string|null  $key
     *
     * @return bool
     */
    public function isCropped(?string $key = null): bool
    {
        if ($key === null) {
            // Check if any crop exists by looking for files with cropped_ prefix
            $path = Path::mediaFolderForMedia($this);

            // Get all files in the media folder
            $files = Storage::disk('media')->files($path);

            // Check if any file matches the cropped pattern
            foreach ($files as $file) {
                if (preg_match('/cropped_.*_'.$this->filename.'\.'. $this->extension().'$/', $file)) {
                    return true;
                }
            }
            return false;
        }

        // Check for specific crop key
        return $this->isCroppedForKey($key);
    }

    public function dimensionPrefix(string $prefix = 'sm'): string
    {
        if ( ! $this->sizeable()) {
            return '';
        }

        if ($prefix == 'cropped' || $prefix == 'cropped_') {
            return 'cropped_';
        }

        $modelSettings =  $this->model->mediaclassSettings();
        if (array_key_exists($this->group, $modelSettings)) {
            return $modelSettings[$this->group]['width'].'_';
            // TODO: A voir dans le futur pour combiner avec un sortable [sizes => w,h] ou 'responsive'
        }
        $prefix = array_key_exists($prefix, Config::getSizes()) ? $prefix : array_key_first(Config::getSizes());

        return $prefix ? Config::getSizes()[$prefix]['width'].'_' : '';
    }

    /**
     * Check if a specific crop exists
     *
     * @param  string  $key  The crop key (e.g., 'banner', 'thumbnail')
     *
     * @return bool
     */
    public function isCroppedForKey(string $key): bool
    {
        return Storage::disk('media')->exists(
            Path::mediaFolderForMedia($this).'/'.'cropped_'.$key.'_'.$this->filename.'.'.$this->extension(),
        );
    }

    /**
     * Get URL for a specific crop
     *
     * @param  string  $key  The crop key
     *
     * @return string|null
     */
    public function getCroppedUrl(string $key): ?string
    {
        if (!$this->isCroppedForKey($key)) {
            return null;
        }

        return Storage::disk('media')->url(
            Path::mediaFolderForMedia($this).'/'.'cropped_'.$key.'_'.$this->filename.'.'.$this->extension(),
        );
    }

    /**
     * Get all existing cropped images
     * This scans the filesystem for crops based on the settings
     *
     * @return array
     */
    public function getCroppedImages(): array
    {
        $cropable = $this->settings()['cropable'] ?? [];

        // force associative form: ['thumb'=>[200,200], …]
        if (!is_array($cropable) || isset($cropable[0])) {
            $cropable = ['default' => $cropable];
        }

        $out = [];
        foreach ($cropable as $key => $dim) {
            if ($this->isCroppedForKey($key)) {
                $out[$key] = [
                    'width'    => $dim[0],
                    'height'   => $dim[1],
                    'filename' => "cropped_{$key}_{$this->filename}.{$this->extension()}",
                ];
            }
        }

        return $out;
    }

}