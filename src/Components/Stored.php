<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Components;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use MetaFramework\Mediaclass\Contracts\MediaclassInterface;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Support\BridgeMedia;

class Stored extends Component
{
    protected array $positionning = [
        'left', 'up', 'down', 'right',
    ];

    public Collection $medias;

    public array $mediaLocales = [];

    public array $videoPreviews = [];

    public function __construct(
        public MediaclassInterface $model,
        public string $group,
        public ?string $subgroup = null,
        public bool $positions = false,
        public int|bool $description = true,
        public array|string|null $cropable = null,
        public string $nomedia = '',
        public bool $ghost = false,
        public array $storables = [],
        public int $grid = 1,
    ) {
        $this->description = $this->description ? 1 : 0;
        $this->storables = $this->sanitizeStorables($this->storables);
        $this->grid = $this->normalizeGrid($this->grid);
        $this->mediaLocales = $this->resolveMediaLocales();

        if ($this->ghost) {
            // For ghost models, don't query the relationship
            // Instead, query Media directly using model type and group
            $modelType = get_class($this->model);
            $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
            $morphType = array_search($modelType, $morphMap) ?: $modelType;

            $query = Media::where('model_type', $morphType)
                ->where('group', $this->group)
                ->whereNull('model_id'); // Ghost records have no model_id

            if ($this->subgroup) {
                $query->where('subgroup', $this->subgroup);
            }

            $this->medias = $query->get();

            // Inject the ghost model into each media
            $this->medias->each(function ($media) {
                $media->setRelation('model', $this->model);
            });
        } else {
            $this->medias = $this->model->media->where('group', $this->group);

            if ($this->subgroup) {
                $this->medias = $this->medias->where('subgroup', $this->subgroup);
            }
        }

        if ($this->storables !== []) {
            $this->medias = $this->medias
                ->filter(fn (Media $media) => $this->mediaMatchesStorables($media))
                ->values();
        }

        $this->medias = collect($this->medias->values()->all())
            ->concat($this->bridgeMedia())
            ->values();
        $this->videoPreviews = $this->medias
            ->filter(fn (mixed $media): bool => $media instanceof Media && $media->isVideo())
            ->mapWithKeys(fn (Media $media): array => [
                (string) $media->id => mediaclass_thumbnail($media),
            ])
            ->filter()
            ->all();

        $this->nomedia = $this->nomedia ?: __('mfw-mediaclass.no_media');

        // Check if model has mediaclassSettings for this group
        if ($this->cropable === null && method_exists($this->model, 'mediaclassSettings')) {
            $modelSettings = $this->model->mediaclassSettings();

            if (isset($modelSettings[$this->group])) {
                $groupSettings = $modelSettings[$this->group];
                $requiredWidth = $groupSettings['width'] ?? null;
                $requiredHeight = $groupSettings['height'] ?? null;

                if ((!$requiredWidth || !$requiredHeight) && isset($groupSettings['sizes']) && is_array($groupSettings['sizes'])) {
                    $sizes = [];
                    foreach ($groupSettings['sizes'] as $size) {
                        if (is_array($size) && isset($size['width'], $size['height'])) {
                            $sizes[] = [(int) $size['width'], (int) $size['height']];
                        }
                    }
                    usort($sizes, fn (array $a, array $b) => $b[0] <=> $a[0]);
                    if (!empty($sizes)) {
                        $requiredWidth = $sizes[0][0];
                        $requiredHeight = $sizes[0][1];
                    }
                }

                // Set cropable if defined in group settings
                if (isset($groupSettings['cropable'])) {
                    if ($groupSettings['cropable'] === true
                        && $requiredWidth && $requiredHeight) {
                        $this->cropable = [
                            $this->group => [
                                $requiredWidth,
                                $requiredHeight,
                            ],
                        ];
                    } elseif (is_array($groupSettings['cropable'])) {
                        $this->cropable = $groupSettings['cropable'];
                    }
                }
            }
        }

        // Convert array to JSON for data attribute
        if (is_array($this->cropable)) {
            $this->cropable = json_encode($this->cropable);
        }
    }

    public function isFile(Media $media): bool
    {
        return !str_contains($media->mime, 'image');
    }

    public function isImage(Media $media): bool
    {
        return str_contains($media->mime, 'image');
    }

    public function render(): Renderable
    {
        return view('mediaclass::components.stored');
    }

    public function getPositionning(): array
    {
        return $this->positionning;
    }

    private function sanitizeStorables(array $storables): array
    {
        return collect($storables)
            ->map(static function ($value) {
                if (is_string($value)) {
                    $trimmed = trim($value);

                    return $trimmed === '' ? null : $trimmed;
                }

                return $value;
            })
            ->filter(static fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private function normalizeGrid(mixed $grid): int
    {
        $grid = (int) $grid;

        if ($grid < 1) {
            return 1;
        }

        if ($grid > 4) {
            return 4;
        }

        return $grid;
    }

    private function resolveMediaLocales(): array
    {
        if (class_exists(\MetaFramework\Accessors\Locale::class)) {
            try {
                $locales = \MetaFramework\Accessors\Locale::projectLocales();
            } catch (\Throwable) {
                $locales = null;
            }

            if (is_array($locales) && $locales !== []) {
                return $locales;
            }
        }

        return config('mfw.locales', config('app.locales', [app()->getLocale()]));
    }

    private function mediaMatchesStorables(Media $media): bool
    {
        if ($this->storables === []) {
            return true;
        }

        $mediaStorables = (array) ($media->storable ?? []);

        foreach ($this->storables as $key => $expected) {
            $mediaValue = $mediaStorables[$key] ?? null;

            if (is_string($mediaValue)) {
                $mediaValue = trim($mediaValue);
            }

            if ($mediaValue != $expected) {
                return false;
            }
        }

        return true;
    }

    private function bridgeMedia(): Collection
    {
        if (!method_exists($this->model, 'mediaclassBridgeMedia')) {
            return collect();
        }

        return collect($this->model->mediaclassBridgeMedia($this->group, $this->subgroup))
            ->map(static function (mixed $media): ?BridgeMedia {
                if ($media instanceof BridgeMedia) {
                    return $media;
                }

                if (is_array($media)) {
                    return BridgeMedia::fromArray($media);
                }

                return null;
            })
            ->filter()
            ->values();
    }
}
