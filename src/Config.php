<?php

namespace MetaFramework\Mediaclass;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config as ConfigFacade;
use Illuminate\Support\Facades\Storage;
use MetaFramework\Mediaclass\Config as MediaclassConfig;
use MetaFramework\Mediaclass\Interfaces\MediaclassInterface;

class Config
{
    private static array $sizes
        = [

            'xl' => [
                'width'  => 1920,
                'height' => 1080,
            ],
            'lg' => [
                'width'  => 1400,
                'height' => 788,
            ],
            'md' => [
                'width'  => 700,
                'height' => 394,
            ],
            'sm' => [
                'width'  => 400,
                'height' => 225,
            ],
        ];

    public static function getSizes(): array
    {
        $sizes = self::config('dimensions', self::$sizes);

        uasort($sizes, function ($a, $b) {
            return $a['width'] <=> $b['width'];
        });

        return $sizes;
    }

    public static function getModelSizes(MediaclassInterface $model): array
    {
        if (method_exists($model, 'mediaclassSettings')) {
            return $model->mediaClassSettings();
        }

        return [];
    }

    public static function getGroupSetings(MediaclassInterface $model, string $group): array
    {
        $sizes = self::getModelSizes($model);
        return isset($sizes[$group]) ? [$group => $sizes[$group]] : [];
    }

    public static function getSizesInReverseOrder(): array
    {
        $sizes = Config::getSizes();
        uasort($sizes, function ($a, $b) {
            return $b['width'] <=> $a['width'];
        });

        return $sizes;
    }

    public static function getDisk(): Filesystem
    {
        $configured = self::config('disk');
        $disk       = $configured && (array_key_exists($configured, ConfigFacade::get('filesystems.disks'))) ? $configured : 'public';

        return Storage::disk($disk);
    }


    /**
     * Returns the default image URL based on the existence of 'imgholder.png'.
     *
     * @return string The default image URL, either 'imgholder.png' or 'imgholder.svg (copied on package installation)'.
     */
    public static function defaultImgUrl(): string
    {
        $default = 'imgholder.png';

        $disk = Config::getDisk();

        return $disk->exists($default) ?
            $disk->url($default)
            : $disk->url('imgholder.svg');
    }

    /**
     * Returns the default group label
     *
     * @return string
     */
    public static function defaultGroup(): string
    {
        return 'media';
    }

    public static function getMinSize(): int
    {
        return min(array_column(Config::getSizes(), 'width'));
    }

    public static function getMaxSize(): int
    {
        return max(array_column(Config::getSizes(), 'width'));
    }

    public static function getDefaultKeys(): array
    {
        return array_keys(self::$sizes);
}

    private static function config(string $key, mixed $default = null): mixed
    {
        $prefixed = ConfigFacade::get('mfw.mediaclass.' . $key);

        if ($prefixed !== null) {
            return $prefixed;
        }

        return ConfigFacade::get('mediaclass.' . $key, $default);
    }
}
