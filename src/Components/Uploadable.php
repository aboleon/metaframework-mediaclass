<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Components;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use MetaFramework\Mediaclass\Support\Config as MediaclassConfig;

class Uploadable extends Component
{
    public ?int $requiredWidth = null;

    public ?int $requiredHeight = null;

    public string $displayLabel = '';

    public string $dimensionsInline = '';

    public array $mediaTypeOptions = [];

    public string $mediaTypeInputName;

    public array $mediaLocales = [];

    public function __construct(
        public object $model,
        public bool $positions = false,
        public string $group = 'media',
        public string $size = '',
        public string $label = '',
        public int|bool $description = true,
        public array|string|null $cropable = null,
        /**
         * @var int
         *          max number of files to be uploaded
         */
        public int $limit = 0,
        /**
         * @var string|null
         *                  ex 500KB, 5MB (default is 16MB)
         */
        public ?string $maxfilesize = null,
        public array $settings = [],
        public array $storables = [],
        public string $icon = 'bi bi-card-image',
        public string $nomedia = '',
        public bool $ghost = false,
        /**
         * @var string|null
         *                  JavaScript callback function name to call after successful upload
         *                  The function will receive the upload response data as parameter
         */
        public ?string $callback = null,
        public array|string $mediaTypes = ['image'],
        public int $grid = 1,
    ) {
        $this->group = $this->settings['group'] ?? $this->group;
        $this->label = $this->settings['label'] ?? $this->label;
        $this->label = $this->label ?: __('mfw-mediaclass.labels.media');
        $this->mediaTypes = $this->settings['media_types'] ?? $this->settings['mediaTypes'] ?? $this->mediaTypes;
        $this->mediaTypeOptions = $this->normalizeMediaTypes($this->mediaTypes);
        $this->grid = $this->normalizeGrid($this->settings['grid'] ?? $this->grid);
        $this->mediaTypeInputName = 'mediaclass_media_type_' . Str::random(10);
        $this->mediaLocales = $this->resolveMediaLocales();
        $this->description = $this->description ? 1 : 0;
        $this->nomedia = $this->nomedia ?: __('mfw-mediaclass.no_media');

        // Check if model has mediaclassSettings for this group
        if ($this->cropable === null && method_exists($this->model, 'mediaclassSettings')) {
            $modelSettings = $this->model->mediaclassSettings();

            if (isset($modelSettings[$this->group])) {
                $groupSettings = $modelSettings[$this->group];
                $groupSizes = [];
                if (isset($groupSettings['sizes']) && is_array($groupSettings['sizes'])) {
                    foreach ($groupSettings['sizes'] as $value) {
                        if (is_array($value) && isset($value['width'], $value['height'])) {
                            $groupSizes[] = [
                                (int) $value['width'],
                                (int) $value['height'],
                            ];
                        }
                    }
                    usort($groupSizes, fn (array $a, array $b) => $b[0] <=> $a[0]);
                }

                // Set label from group settings if available
                if (isset($groupSettings['label'])) {
                    $this->label = $groupSettings['label'];
                }

                // Extract dimensions
                if (isset($groupSettings['width']) && isset($groupSettings['height'])) {
                    $this->requiredWidth = $groupSettings['width'];
                    $this->requiredHeight = $groupSettings['height'];
                } elseif (!empty($groupSizes)) {
                    $this->requiredWidth = $groupSizes[0][0];
                    $this->requiredHeight = $groupSizes[0][1];
                }

                // Set cropable if defined in group settings
                // Note: cropable will be dynamically determined based on uploaded image dimensions
                // This is just a fallback for the component initialization
                if (isset($groupSettings['cropable'])) {
                    if ($groupSettings['cropable'] === true
                        && $this->requiredWidth && $this->requiredHeight) {
                        $this->cropable = [
                            $this->group => [
                                $this->requiredWidth,
                                $this->requiredHeight,
                            ],
                        ];
                    } elseif (is_array($groupSettings['cropable'])) {
                        $this->cropable = $groupSettings['cropable'];
                    }
                }
            }
        }

        // Extract dimensions from settings array if available
        if (!$this->requiredWidth && !$this->requiredHeight && isset($this->settings['sizes']) && is_array($this->settings['sizes'])) {
            $this->requiredWidth = (int) $this->settings['sizes'][0];
            $this->requiredHeight = (int) $this->settings['sizes'][1];
        }

        $dimensions = [];
        if (method_exists($this->model, 'mediaclassSettings')) {
            $modelSettings = $this->model->mediaclassSettings();
            if (isset($modelSettings[$this->group]['sizes']) && is_array($modelSettings[$this->group]['sizes'])) {
                foreach ($modelSettings[$this->group]['sizes'] as $size) {
                    if (is_array($size) && isset($size['width'], $size['height'])) {
                        $dimensions[] = [(int) $size['width'], (int) $size['height']];
                    }
                }
                usort($dimensions, fn (array $a, array $b) => $b[0] <=> $a[0]);
            } elseif (isset($modelSettings[$this->group]['width'], $modelSettings[$this->group]['height'])) {
                $dimensions[] = [
                    (int) $modelSettings[$this->group]['width'],
                    (int) $modelSettings[$this->group]['height'],
                ];
            }
        }

        if (empty($dimensions) && $this->requiredWidth && $this->requiredHeight) {
            $dimensions[] = [$this->requiredWidth, $this->requiredHeight];
        }

        if (empty($dimensions)) {
            foreach (MediaclassConfig::getSizesInReverseOrder() as $size) {
                $dimensions[] = [(int) $size['width'], (int) $size['height']];
            }
        }

        $this->dimensionsInline = implode(', ', array_map(
            fn (array $pair) => $pair[0] . '×' . $pair[1],
            $dimensions
        ));

        // Build display label with dimensions
        $this->displayLabel = $this->label;
        /* if ($this->requiredWidth && $this->requiredHeight) {
             $this->displayLabel .= ' <span class="dimensions-info" style="background: rgba(0,0,0,0.1); padding: 2px 8px; border-radius: 4px;">(' . $this->requiredWidth . ' × ' . $this->requiredHeight . ' px)</span>';
         }*/
        if (is_array($this->cropable)) {
            $this->cropable = json_encode($this->cropable);
        }
    }

    private function normalizeMediaTypes(array|string $mediaTypes): array
    {
        if (is_string($mediaTypes)) {
            $mediaTypes = array_filter(array_map('trim', explode(',', $mediaTypes)));
        }

        $options = [];

        foreach ($mediaTypes as $key => $value) {
            $type = is_int($key) ? (string) $value : (string) $key;
            $label = is_int($key) ? $this->mediaTypeLabel($type) : (string) $value;

            if ($type !== '') {
                $options[$type] = $label !== '' ? $label : $this->mediaTypeLabel($type);
            }
        }

        return $options ?: ['image' => $this->mediaTypeLabel('image')];
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

    private function mediaTypeLabel(string $type): string
    {
        $translation = __('mfw-mediaclass.labels.' . $type);

        return $translation === 'mfw-mediaclass.labels.' . $type ? ucfirst($type) : $translation;
    }

    public function render(): Renderable
    {
        return view('mediaclass::components.uploadable');
    }
}
