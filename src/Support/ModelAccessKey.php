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
        $existing = ModelKey::query()
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->value('access_key');

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

        return $accessKey;
    }

    public static function forMedia(Media $media): ?string
    {
        if (!$media->model_id || !self::tableExists()) {
            return null;
        }

        $existing = ModelKey::query()
            ->where('model_type', $media->model_type)
            ->where('model_id', (int) $media->model_id)
            ->value('access_key');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        return null;
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

    private static function tableExists(): bool
    {
        return Schema::hasTable((new ModelKey)->getTable());
    }
}
