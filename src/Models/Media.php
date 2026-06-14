<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use MetaFramework\Mediaclass\Concerns\Accessors;
use MetaFramework\Mediaclass\MediaBuilder;
use MetaFramework\Mediaclass\Support\Config;
use MetaFramework\Mediaclass\Support\Path;
use Symfony\Component\Mime\MimeTypes;

/**
 * @property string $filename
 * @property string $position
 * @property int $sort_order
 * @property array $description
 * @property string $mime
 * @property int $id
 * @property string $group
 * @property \MetaFramework\Mediaclass\Contracts\MediaclassInterface $model
 */
class Media extends Model
{
    use Accessors;

    public const DEFAULT_EMBED_WIDTH = 560;

    public const DEFAULT_EMBED_HEIGHT = 315;

    protected $table = 'mediaclass';

    protected $guarded = [];

    protected $casts
        = [
            'description' => 'array',
            'storable' => 'array',
            'sort_order' => 'integer',
        ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function extension(): ?string
    {
        if ($this->isExternalUrl()) {
            return 'mov';
        }

        return MimeTypes::getDefault()->getExtensions($this->mime)[0] ?? null;
    }

    public function sizeable(): bool
    {
        return match ($this->mime) {
            'image/png', 'image/jpeg' => true,
            default => false,
        };
    }

    public function url(string $size = 'sm', ?string $cropKey = null): string
    {
        if ($this->isExternalUrl()) {
            return $this->externalUrl() ?? '';
        }

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

        return Config::getDisk()->url(
            Path::ensureMediaFilePathForMedia($this, $size),
        );
    }

    public function file(string $size = 'sm'): string
    {
        return Config::getDisk()->get(Path::ensureMediaFilePathForMedia($this, $size));
    }

    public function isCropped(?string $key = null): bool
    {
        if ($key === null) {
            // Check if any crop exists by looking for files with cropped_ prefix
            $path = Path::mediaFolderForMedia($this);

            // Get all files in the media folder
            $files = Config::getDisk()->files($path);

            // Check if any file matches the cropped pattern
            foreach ($files as $file) {
                if (preg_match('/cropped_.*_' . $this->filename . '\.' . $this->extension() . '$/', $file)) {
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
        if (!$this->sizeable()) {
            return '';
        }

        if ($prefix == 'cropped' || $prefix == 'cropped_') {
            return 'cropped_';
        }

        $modelSettings =  $this->model->mediaclassSettings();
        if (array_key_exists($this->group, $modelSettings)) {
            $groupSettings = $modelSettings[$this->group];

            if (isset($groupSettings['sizes'][$prefix]['width'])) {
                return $groupSettings['sizes'][$prefix]['width'] . '_';
            }

            if (isset($groupSettings['sizes']) && is_array($groupSettings['sizes'])) {
                $groupSizes = Config::getGroupSizes($this->model, $this->group);
                if (!empty($groupSizes)) {
                    $sizes = array_values($groupSizes);
                    usort($sizes, fn ($a, $b) => $b['width'] <=> $a['width']);
                    $largest = $sizes[0] ?? null;
                    if ($largest && isset($largest['width'])) {
                        return $largest['width'] . '_';
                    }
                }
            }

            if (isset($groupSettings['width'])) {
                return $groupSettings['width'] . '_';
            }
            // TODO: A voir dans le futur pour combiner avec un sortable [sizes => w,h] ou 'responsive'
        }
        $prefix = array_key_exists($prefix, Config::getSizes()) ? $prefix : array_key_first(Config::getSizes());

        return $prefix ? Config::getSizes()[$prefix]['width'] . '_' : '';
    }

    public function resolveSizeKey(string $sizeKey = 'sm'): string
    {
        if (!$this->sizeable() || $sizeKey === 'cropped' || str_starts_with($sizeKey, 'cropped_')) {
            return $sizeKey;
        }

        $groupSizes = Config::getGroupSizes($this->model, $this->group);

        if ($groupSizes === []) {
            return $sizeKey;
        }

        if (array_key_exists($sizeKey, $groupSizes)) {
            return $sizeKey;
        }

        return $this->closestGroupSizeKey($groupSizes, $sizeKey) ?? (string) array_key_last($groupSizes);
    }

    /**
     * @param  array<string, array{width: int, height: int}>  $groupSizes
     */
    private function closestGroupSizeKey(array $groupSizes, string $sizeKey): ?string
    {
        $defaultSizes = Config::getSizes();
        $targetWidth = $defaultSizes[$sizeKey]['width'] ?? null;

        if (!$targetWidth) {
            return null;
        }

        $closestKey = null;
        $closestWidth = null;

        foreach ($groupSizes as $key => $size) {
            $width = $size['width'] ?? null;

            if (!$width || $width < $targetWidth) {
                continue;
            }

            if ($closestWidth === null || $width < $closestWidth) {
                $closestKey = $key;
                $closestWidth = $width;
            }
        }

        if ($closestKey !== null) {
            return (string) $closestKey;
        }

        foreach ($groupSizes as $key => $size) {
            $width = $size['width'] ?? null;

            if (!$width) {
                continue;
            }

            if ($closestWidth === null || $width > $closestWidth) {
                $closestKey = $key;
                $closestWidth = $width;
            }
        }

        return $closestKey === null ? null : (string) $closestKey;
    }

    /**
     * Check if a specific crop exists
     *
     * @param  string  $key  The crop key (e.g., 'banner', 'thumbnail')
     */
    public function isCroppedForKey(string $key): bool
    {
        return Config::getDisk()->exists(
            Path::mediaFolderForMedia($this) . '/' . 'cropped_' . $key . '_' . $this->filename . '.' . $this->extension(),
        );
    }

    /**
     * Get URL for a specific crop
     *
     * @param  string  $key  The crop key
     */
    public function getCroppedUrl(string $key): ?string
    {
        if (!$this->isCroppedForKey($key)) {
            return null;
        }

        return Config::getDisk()->url(
            Path::mediaFolderForMedia($this) . '/' . 'cropped_' . $key . '_' . $this->filename . '.' . $this->extension(),
        );
    }

    /**
     * Get all existing cropped images
     * This scans the filesystem for crops based on the settings
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

    // -------------------------------------------------------------------------
    // Fluent Builder Methods
    // -------------------------------------------------------------------------

    /**
     * Get a fluent MediaBuilder for this media.
     *
     * @example
     * $media->builder()->class('rounded')->lazy()->img();
     */
    public function builder(): MediaBuilder
    {
        return new MediaBuilder($this);
    }

    /**
     * Shorthand: Check if crop exists (alias for isCroppedForKey).
     */
    public function hasCrop(string $key): bool
    {
        return $this->isCroppedForKey($key);
    }

    /**
     * Shorthand: Get crop URL (alias for getCroppedUrl).
     */
    public function crop(string $key): ?string
    {
        return $this->getCroppedUrl($key);
    }

    /**
     * Get all available size keys.
     */
    public function sizes(): array
    {
        $groupSettings = Config::getGroupSettings($this->model, $this->group);
        if (isset($groupSettings['sizes']) && is_array($groupSettings['sizes'])) {
            return array_keys($groupSettings['sizes']);
        }

        return array_keys(Config::getSizes());
    }

    /**
     * Check if this is an image.
     */
    public function isImage(): bool
    {
        return str_contains($this->mime, 'image');
    }

    public function isVideo(): bool
    {
        return str_contains($this->mime, 'video');
    }

    public function isExternalUrl(): bool
    {
        return str_contains($this->mime, 'url') && $this->externalUrl() !== null;
    }

    public function externalUrl(): ?string
    {
        $url = (array) ($this->storable ?? []);
        $url = $url['url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function thumbnailUrl(): ?string
    {
        $thumbnail = ((array) ($this->storable ?? []))['thumbnail_url'] ?? null;

        if (!is_string($thumbnail)) {
            return null;
        }

        $thumbnail = trim($thumbnail);
        $scheme = parse_url($thumbnail, PHP_URL_SCHEME);

        if (
            filter_var($thumbnail, FILTER_VALIDATE_URL) === false
            || !is_string($scheme)
            || !in_array(strtolower($scheme), ['http', 'https'], true)
        ) {
            return null;
        }

        return $thumbnail;
    }

    public function embedWidth(): int|string
    {
        return self::normalizeEmbedWidth(((array) ($this->storable ?? []))['embed_width'] ?? null);
    }

    public function embedHeight(): int
    {
        return self::normalizeEmbedHeight(((array) ($this->storable ?? []))['embed_height'] ?? null);
    }

    /**
     * @return array{width: int|string, height: int}
     */
    public function embedOptions(): array
    {
        return [
            'width' => $this->embedWidth(),
            'height' => $this->embedHeight(),
        ];
    }

    public static function normalizeEmbedWidth(mixed $width): int|string
    {
        if (is_string($width) && trim($width) === '100%') {
            return '100%';
        }

        $width = filter_var($width, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => 7680,
            ],
        ]);

        return $width === false ? self::DEFAULT_EMBED_WIDTH : $width;
    }

    public static function normalizeEmbedHeight(mixed $height): int
    {
        $height = filter_var($height, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => 4320,
            ],
        ]);

        return $height === false ? self::DEFAULT_EMBED_HEIGHT : $height;
    }

    /**
     * Render as img tag with optional attributes.
     *
     * @example
     * {!! $media->img() !!}
     * {!! $media->img('lg', ['class' => 'rounded']) !!}
     */
    public function img(string $size = 'sm', array $attributes = []): string
    {
        return (string) $this->builder()->size($size)->attrs($attributes)->img();
    }
}
