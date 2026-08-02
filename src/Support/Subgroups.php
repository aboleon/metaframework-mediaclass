<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Support;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use MetaFramework\Mediaclass\Contracts\MediaclassInterface;
use MetaFramework\Mediaclass\Models\Media;

class Subgroups
{
    /**
     * @param  array<string, string>  $componentOptions
     * @return array{options: array<string, string>, label: string, empty_label: string}
     */
    public static function definition(MediaclassInterface $model, string $group, array $settings = [], array $componentOptions = []): array
    {
        $definition = $componentOptions !== []
            ? ['options' => $componentOptions]
            : self::definitionSource($model, $group, $settings);

        return self::normalizeDefinition($definition);
    }

    /**
     * @param  array<string, string>  $componentOptions
     * @return array<string, string>
     */
    public static function options(MediaclassInterface $model, string $group, array $settings = [], array $componentOptions = []): array
    {
        return self::definition($model, $group, $settings, $componentOptions)['options'];
    }

    /**
     * @return array<int, string>
     */
    public static function values(MediaclassInterface $model, string $group, bool $ghost = false): array
    {
        if (!$ghost && self::modelKey($model) === null) {
            return [];
        }

        return self::mediaQuery($model, $group, $ghost)
            ->whereNotNull('subgroup')
            ->get(['id', 'subgroup'])
            ->mapWithKeys(fn (Media $media): array => [(int)$media->id => (string)$media->subgroup])
            ->all();
    }

    public static function active(MediaclassInterface $model, string $group, bool $ghost = false): bool
    {
        if (!$ghost && self::modelKey($model) === null) {
            return false;
        }

        return self::mediaQuery($model, $group, $ghost)
            ->whereNotNull('subgroup')
            ->exists();
    }

    public static function mediaQuery(MediaclassInterface $model, string $group, bool $ghost = false): \Illuminate\Database\Eloquent\Builder
    {
        $morphable = self::morphable($model);
        $modelId = self::modelKey($model);

        return Media::query()
            ->where('model_type', $morphable)
            ->where('group', $group)
            ->when(
                $ghost,
                fn ($query) => $query->whereNull('model_id'),
                fn ($query) => $query->where('model_id', $modelId),
            );
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private static function definitionSource(MediaclassInterface $model, string $group, array $settings): mixed
    {
        if (array_key_exists('subgroups', $settings)) {
            return $settings['subgroups'];
        }

        if (method_exists($model, 'mediaclassSettings')) {
            $modelSettings = (array)$model->mediaclassSettings();

            if (array_key_exists('subgroups', (array)($modelSettings[$group] ?? []))) {
                return $modelSettings[$group]['subgroups'];
            }
        }

        return config('mfw-mediaclass.subgroups.groups.' . $group, []);
    }

    /**
     * @return array{options: array<string, string>, label: string, empty_label: string}
     */
    private static function normalizeDefinition(mixed $definition): array
    {
        $defaults = (array)config('mfw-mediaclass.subgroups', []);
        $label = (string)data_get($defaults, 'label', __('mfw-mediaclass::messages.labels.subgroup'));
        $emptyLabel = (string)data_get($defaults, 'empty_label', __('mfw-mediaclass::messages.labels.no_subgroup'));

        if ($definition === true) {
            $definition = [
                'count' => (int)data_get($defaults, 'count', 5),
            ];
        }

        if (is_numeric($definition)) {
            $definition = [
                'count' => (int)$definition,
            ];
        }

        if (!is_array($definition) || (array_key_exists('enabled', $definition) && !$definition['enabled'])) {
            return [
                'options' => [],
                'label' => $label,
                'empty_label' => $emptyLabel,
            ];
        }

        $label = (string)($definition['label'] ?? $label);
        $emptyLabel = (string)($definition['empty_label'] ?? $emptyLabel);

        return [
            'options' => self::normalizeOptions($definition),
            'label' => $label !== '' ? $label : __('mfw-mediaclass::messages.labels.subgroup'),
            'empty_label' => $emptyLabel !== '' ? $emptyLabel : __('mfw-mediaclass::messages.labels.no_subgroup'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function normalizeOptions(array $definition): array
    {
        $configuredOptions = (array)($definition['options'] ?? []);

        if ($configuredOptions === [] && !self::containsDefinitionKeys($definition)) {
            $configuredOptions = $definition;
        }

        if ($configuredOptions !== []) {
            return collect($configuredOptions)
                ->mapWithKeys(fn (mixed $label, int|string $key): array => [(string)$key => (string)$label])
                ->filter(fn (string $label, string $key): bool => $key !== '' && $label !== '')
                ->all();
        }

        $count = max(0, min(20, (int)($definition['count'] ?? config('mfw-mediaclass.subgroups.count', 0))));
        $label = trim((string)($definition['label'] ?? config('mfw-mediaclass.subgroups.label', __('mfw-mediaclass::messages.labels.subgroup'))));
        $keyPrefix = trim((string)($definition['key_prefix'] ?? config('mfw-mediaclass.subgroups.key_prefix', 'group_')));

        if ($count < 1) {
            return [];
        }

        $label = $label !== '' ? $label : __('mfw-mediaclass::messages.labels.subgroup');
        $keyPrefix = $keyPrefix !== '' ? $keyPrefix : 'group_';

        return Collection::make(range(1, $count))
            ->mapWithKeys(fn (int $number): array => [$keyPrefix . $number => $label . ' ' . $number])
            ->all();
    }

    private static function morphable(MediaclassInterface $model): string
    {
        $class = get_class($model);

        return Relation::morphMap()
            ? array_search($class, Relation::morphMap(), true) ?: $class
            : $class;
    }

    private static function modelKey(MediaclassInterface $model): mixed
    {
        return method_exists($model, 'getKey') ? $model->getKey() : ($model->id ?? null);
    }

    private static function containsDefinitionKeys(array $definition): bool
    {
        foreach (array_keys($definition) as $key) {
            if (in_array($key, [
                'enabled',
                'count',
                'empty_label',
                'key_prefix',
                'label',
                'options',
            ], true)) {
                return true;
            }
        }

        return false;
    }
}
