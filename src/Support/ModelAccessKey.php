<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Support;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use MetaFramework\Mediaclass\Contracts\MediaclassInterface;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Models\ModelKey;

class ModelAccessKey
{
    private const KEY_LENGTH = 8;

    private static ?bool $modelKeysTableExists = null;

    /**
     * @var array<string, string|null>
     */
    private static array $accessKeysByModel = [];

    public static function forModel(MediaclassInterface $model, ?string $preferredKey = null, bool $create = true): ?string
    {
        if (!$model instanceof EloquentModel || !$model->exists || !$model->getKey()) {
            return null;
        }

        if (!self::tableExists()) {
            return null;
        }

        $modelType = self::modelType($model);
        $modelId = (int) $model->getKey();
        $existing = self::existingAccessKey($modelType, $modelId);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        if (!$create) {
            return null;
        }

        $accessKey = self::availableAccessKey($modelType, $modelId, $preferredKey);

        ModelKey::query()->create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'access_key' => $accessKey,
        ]);

        self::$accessKeysByModel[self::cacheKey($modelType, $modelId)] = $accessKey;

        return $accessKey;
    }

    public static function forMedia(Media $media): ?string
    {
        if (!$media->model_id || !self::tableExists()) {
            return null;
        }

        $existing = self::existingAccessKey((string) $media->model_type, (int) $media->model_id);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        return null;
    }

    public static function preloadForModels(iterable $models): void
    {
        $modelIdsByType = [];

        foreach ($models as $model) {
            if (!$model instanceof MediaclassInterface || !$model instanceof EloquentModel || !$model->exists || !$model->getKey()) {
                continue;
            }

            $modelType = self::modelType($model);
            $modelIdsByType[$modelType][(int) $model->getKey()] = (int) $model->getKey();
        }

        self::preloadAccessKeys($modelIdsByType);
    }

    public static function preloadForMedia(iterable $mediaItems): void
    {
        $modelIdsByType = [];

        foreach ($mediaItems as $media) {
            if (!$media instanceof Media || !$media->model_id) {
                continue;
            }

            $modelType = (string) $media->model_type;
            $modelIdsByType[$modelType][(int) $media->model_id] = (int) $media->model_id;
        }

        self::preloadAccessKeys($modelIdsByType);
    }

    public static function flushCache(): void
    {
        self::$modelKeysTableExists = null;
        self::$accessKeysByModel = [];
    }

    public static function modelType(EloquentModel $model): string
    {
        $alias = array_search($model::class, Relation::morphMap(), true);

        return $alias === false ? $model::class : (string) $alias;
    }

    private static function availableAccessKey(string $modelType, int $modelId, ?string $preferredKey = null): string
    {
        $preferredKey = self::normalize($preferredKey);

        if ($preferredKey !== null && self::accessKeyAvailable($preferredKey, $modelType, $modelId)) {
            return $preferredKey;
        }

        do {
            $accessKey = Str::random(self::KEY_LENGTH);
        } while (!self::accessKeyAvailable($accessKey, $modelType, $modelId));

        return $accessKey;
    }

    private static function accessKeyAvailable(string $accessKey, string $modelType, int $modelId): bool
    {
        return !ModelKey::query()
            ->where('access_key', $accessKey)
            ->where(function ($query) use ($modelType, $modelId): void {
                $query
                    ->where('model_type', '!=', $modelType)
                    ->orWhere('model_id', '!=', $modelId);
            })
            ->exists();
    }

    private static function normalize(?string $accessKey): ?string
    {
        $accessKey = trim((string) $accessKey, "/\\ \t\n\r\0\x0B");

        return $accessKey === '' ? null : $accessKey;
    }

    private static function existingAccessKey(string $modelType, int $modelId): ?string
    {
        $cacheKey = self::cacheKey($modelType, $modelId);

        if (array_key_exists($cacheKey, self::$accessKeysByModel)) {
            return self::$accessKeysByModel[$cacheKey];
        }

        $existing = ModelKey::query()
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->value('access_key');

        self::$accessKeysByModel[$cacheKey] = is_string($existing) && $existing !== '' ? $existing : null;

        return self::$accessKeysByModel[$cacheKey];
    }

    /**
     * @param  array<string, array<int, int>>  $modelIdsByType
     */
    private static function preloadAccessKeys(array $modelIdsByType): void
    {
        if ($modelIdsByType === [] || !self::tableExists()) {
            return;
        }

        $pendingModelIdsByType = [];

        foreach ($modelIdsByType as $modelType => $modelIds) {
            foreach (array_filter(array_unique($modelIds)) as $modelId) {
                $modelId = (int) $modelId;
                $cacheKey = self::cacheKey($modelType, $modelId);

                if (!array_key_exists($cacheKey, self::$accessKeysByModel)) {
                    $pendingModelIdsByType[$modelType][$modelId] = $modelId;
                    self::$accessKeysByModel[$cacheKey] = null;
                }
            }
        }

        if ($pendingModelIdsByType === []) {
            return;
        }

        $keys = ModelKey::query()
            ->where(function ($query) use ($pendingModelIdsByType): void {
                foreach ($pendingModelIdsByType as $modelType => $modelIds) {
                    $query->orWhere(function ($query) use ($modelType, $modelIds): void {
                        $query
                            ->where('model_type', $modelType)
                            ->whereIn('model_id', array_values($modelIds));
                    });
                }
            })
            ->get(['model_type', 'model_id', 'access_key']);

        foreach ($keys as $key) {
            $accessKey = (string) $key->access_key;

            if ($accessKey !== '') {
                self::$accessKeysByModel[self::cacheKey((string) $key->model_type, (int) $key->model_id)] = $accessKey;
            }
        }
    }

    private static function cacheKey(string $modelType, int $modelId): string
    {
        return $modelType . ':' . $modelId;
    }

    private static function tableExists(): bool
    {
        return self::$modelKeysTableExists ??= Schema::hasTable((new ModelKey)->getTable());
    }
}
