<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Support;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use MetaFramework\Mediaclass\Contracts\MediaclassInterface;
use MetaFramework\Mediaclass\Models\Media;
use ReflectionClass;

class Path
{
    public static function mediaFolderName(MediaclassInterface $model): string
    {
        if ($accessKey = ModelAccessKey::forModel($model, self::customMediaFolderName($model))) {
            return $accessKey;
        }

        if ($folder = self::customMediaFolderName($model)) {
            return $folder;
        }

        return self::defaultMediaFolderName($model);
    }

    public static function defaultMediaFolderName(MediaclassInterface $model): string
    {
        return Str::snake((new ReflectionClass($model))->getShortName()) . '/' . ($model->id ?? 'temp');
    }

    public static function mediaTempFolderName(MediaclassInterface $model): string
    {
        return Str::snake((new ReflectionClass($model))->getShortName()) . '/temp';
    }

    private static function customMediaFolderName(MediaclassInterface $model): ?string
    {
        if (method_exists($model, 'mediaclassFolderName')) {
            $folder = trim((string) $model->mediaclassFolderName(), '/\\');

            if ($folder !== '') {
                return $folder;
            }
        }

        return null;
    }

    /**
     * Get the folder path for a media record, handling ghost models
     */
    public static function mediaFolderForMedia(Media $media): string
    {
        if ($accessKey = ModelAccessKey::forMedia($media)) {
            return $accessKey;
        }

        // For ghost models, use just the model name folder
        if ($media->model_id === null) {
            $modelClass = $media->model_type;
            $morphMap = Relation::morphMap();

            if (!empty($morphMap)) {
                $modelClass = $morphMap[$modelClass] ?? $modelClass;
            }

            $modelName = class_exists($modelClass)
                ? (new ReflectionClass($modelClass))->getShortName()
                : class_basename($modelClass);

            return Str::snake($modelName);
        }

        // For regular models, use the standard path with ID
        return self::mediaFolderName($media->model);
    }

    public static function mediaFileName(
        MediaclassInterface $model,
        string $filename,
        string $extension,
        ?string $sizeKey = null,
        ?int $width = null,
        ?Media $media = null,
        bool $allowCustomName = true,
    ): string {
        if ($allowCustomName && method_exists($model, 'mediaclassFileName')) {
            $customFilename = trim((string) $model->mediaclassFileName($filename, $extension, $sizeKey, $media));

            if ($customFilename !== '') {
                return $customFilename;
            }
        }

        if ($media && $media->sizeable()) {
            return $media->dimensionPrefix($sizeKey ?? '') . $filename . '.' . $extension;
        }

        if ($width) {
            return $width . '_' . $filename . '.' . $extension;
        }

        return $filename . '.' . $extension;
    }

    public static function mediaFilePath(
        MediaclassInterface $model,
        string $filename,
        string $extension,
        ?string $sizeKey = null,
        ?int $width = null,
        ?Media $media = null,
    ): string {
        return self::mediaFolderName($model) . '/' . self::mediaFileName($model, $filename, $extension, $sizeKey, $width, $media);
    }

    public static function mediaFilePathForMedia(Media $media, string $sizeKey): string
    {
        $resolvedSizeKey = $media->resolveSizeKey($sizeKey);

        return self::mediaFolderForMedia($media) . '/' . self::mediaFileName(
            $media->model,
            $media->filename,
            $media->extension() ?? '',
            $resolvedSizeKey,
            null,
            $media,
        );
    }

    public static function defaultMediaFilePathForMedia(Media $media, string $sizeKey): string
    {
        $resolvedSizeKey = $media->resolveSizeKey($sizeKey);

        return self::defaultMediaFolderName($media->model) . '/' . self::mediaFileName(
            $media->model,
            $media->filename,
            $media->extension() ?? '',
            $resolvedSizeKey,
            null,
            $media,
            false,
        );
    }

    public static function ensureMediaFilePathForMedia(Media $media, string $sizeKey): string
    {
        $path = self::mediaFilePathForMedia($media, $sizeKey);
        $defaultPath = self::defaultMediaFilePathForMedia($media, $sizeKey);

        if ($path === $defaultPath) {
            return $path;
        }

        $disk = Config::getDisk();

        if (!$disk->exists($path) && $disk->exists($defaultPath)) {
            $disk->makeDirectory(dirname($path));
            $disk->copy($defaultPath, $path);
        }

        return $path;
    }

    public static function checkMakeDir(string $directory, int $permissions=0755): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, $permissions, true);
        }
    }
}
