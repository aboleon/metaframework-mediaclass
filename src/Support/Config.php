<?php

namespace MetaFramework\Mediaclass\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config as ConfigFacade;
use Illuminate\Support\Facades\Storage;
use MetaFramework\Mediaclass\Contracts\MediaclassInterface;

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
            return $model->mediaclassSettings();
        }

        return [];
    }

    public static function getGroupSettings(MediaclassInterface $model, string $group): array
    {
        $sizes = self::getModelSizes($model);
        return $sizes[$group] ?? [];
    }

    public static function getGroupSetings(MediaclassInterface $model, string $group): array
    {
        $sizes = self::getModelSizes($model);
        return isset($sizes[$group]) ? [$group => $sizes[$group]] : [];
    }

    public static function getGroupSizes(MediaclassInterface $model, string $group): array
    {
        $settings = self::getGroupSettings($model, $group);

        if (isset($settings['sizes']) && is_array($settings['sizes'])) {
            return self::normalizeSizesArray($settings['sizes']);
        }

        return [];
    }

    public static function getGroupResizeDimensions(MediaclassInterface $model, string $group): array
    {
        $settings = self::getGroupSettings($model, $group);
        $groupSizes = self::getGroupSizes($model, $group);

        if (!empty($groupSizes)) {
            return self::sortSizesByWidthDesc($groupSizes);
        }

        if (isset($settings['width'], $settings['height'])) {
            return [
                $group => [
                    'width' => (int) $settings['width'],
                    'height' => (int) $settings['height'],
                ],
            ];
        }

        return self::getSizesInReverseOrder();
    }

    public static function getGroupRequiredDimensions(MediaclassInterface $model, string $group): ?array
    {
        $settings = self::getGroupSettings($model, $group);
        $groupSizes = self::getGroupSizes($model, $group);

        if (!empty($groupSizes)) {
            $sorted = self::sortSizesByWidthDesc($groupSizes);
            $first = $sorted[array_key_first($sorted)] ?? null;
            if ($first && isset($first['width'], $first['height'])) {
                return [(int) $first['width'], (int) $first['height']];
            }
        }

        if (isset($settings['width'], $settings['height'])) {
            return [(int) $settings['width'], (int) $settings['height']];
        }

        return null;
    }

    public static function shouldEnforceDimensions(MediaclassInterface $model, string $group): bool
    {
        $settings = self::getGroupSettings($model, $group);
        $enforce = $settings['enforce_dimensions'] ?? $settings['enforceDimensions'] ?? true;

        return filter_var($enforce, FILTER_VALIDATE_BOOL);
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
        $prefixed = ConfigFacade::get('mfw-mediaclass.' . $key);

        if ($prefixed !== null) {
            return $prefixed;
        }

        return ConfigFacade::get('mediaclass.' . $key, $default);
    }

    private static function normalizeSizesArray(array $sizes): array
    {
        $normalized = [];
        foreach ($sizes as $key => $value) {
            if (!is_array($value) || !isset($value['width'], $value['height'])) {
                continue;
            }
            $normalized[$key] = [
                'width' => (int) $value['width'],
                'height' => (int) $value['height'],
            ];
        }

        return $normalized;
    }

    private static function sortSizesByWidthDesc(array $sizes): array
    {
        uasort($sizes, function ($a, $b) {
            return $b['width'] <=> $a['width'];
        });

        return $sizes;
    }
}
