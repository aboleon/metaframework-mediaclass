<?php

namespace MetaFramework\Mediaclass;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\Relation;
use MetaFramework\Mediaclass\Interfaces\MediaclassInterface;
use MetaFramework\Mediaclass\Models\Media;
use ReflectionClass;

class Path
{
    public static function mediaFolderName(MediaclassInterface $model): string
    {
        return Str::snake((new ReflectionClass($model))->getShortName()) . '/' . ($model->id ?? 'temp');
    }

    public static function mediaTempFolderName(MediaclassInterface $model): string
    {
        return Str::snake((new ReflectionClass($model))->getShortName()) . '/temp';
    }

    /**
     * Get the folder path for a media record, handling ghost models
     */
    public static function mediaFolderForMedia(Media $media): string
    {
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

    public static function checkMakeDir(string $directory, int $permissions=0755): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, $permissions, true);
        }
    }
}
