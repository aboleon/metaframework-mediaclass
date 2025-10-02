<?php

namespace MetaFramework\Mediaclass\Components;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;
use MetaFramework\Mediaclass\Interfaces\MediaclassInterface;
use MetaFramework\Mediaclass\Models\Media;

class Stored extends Component
{
    protected array $positionning = [
        'left','up','down','right'
    ];

    public Collection $medias;

    public function __construct(
        public MediaclassInterface $model,
        public string $group,
        public ?string $subgroup = null,
        public bool       $positions = false,
        public int|bool   $description = true,
        public array|string|null $cropable = null,
        public string $nomedia = '',
        public bool $ghost = false,
        public array $storables = []
    )
    {
        $this->description = $this->description ? 1 : 0;
        $this->storables = $this->sanitizeStorables($this->storables);

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
            $this->medias->each(function($media) {
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
                ->filter(fn(Media $media) => $this->mediaMatchesStorables($media))
                ->values();
        }

        $this->nomedia = $this->nomedia ?: __('mediaclass.no_media');

        // Check if model has mediaclassSettings for this group
        if ($this->cropable === null && method_exists($this->model, 'mediaclassSettings')) {
            $modelSettings = $this->model->mediaclassSettings();

            if (isset($modelSettings[$this->group])) {
                $groupSettings = $modelSettings[$this->group];

                // Set cropable if defined in group settings
                if (isset($groupSettings['cropable']) && $groupSettings['cropable'] === true) {
                    $this->cropable = [
                        $this->group => [
                            $groupSettings['width'],
                            $groupSettings['height']
                        ]
                    ];
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
            ->filter(static fn($value) => $value !== null && $value !== '')
            ->all();
    }

    private function mediaMatchesStorables(Media $media): bool
    {
        if ($this->storables === []) {
            return true;
        }

        $mediaStorables = (array)($media->storable ?? []);

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
}
