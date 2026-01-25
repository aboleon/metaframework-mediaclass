<?php

namespace MetaFramework\Mediaclass;

use Illuminate\Support\Facades\Route;
use MetaFramework\Mediaclass\Concerns\Accessors;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Support\Config;
use MetaFramework\Mediaclass\Support\Path;

class Cropable
{
    use Accessors;

    private array $settings = [];
    private array $cropable_settings = [];
    private ?string $current_crop_key = null;
    private int $cropable_width = 0;
    private int $cropable_height = 0;
    public array $croppedImages = [];
    private bool $is_ghost = false;

    public function __construct(public Media $media)
    {
        // Check if this is a ghost media (no model_id)
        $this->is_ghost = $media->model_id === null;

        $this->settings();
        $this->checkIfCropped();
    }

    public function settings(): self
    {
        $this->settings = $this->media->settings();

        // Handle the case where dimensions are defined in the group settings
        if (isset($this->settings['width']) && isset($this->settings['height'])) {
            // Check if cropable is enabled
            if (isset($this->settings['cropable']) && $this->settings['cropable'] === true) {
                // Use the group name as the crop key with the dimensions from settings
                $this->cropable_settings = [
                    $this->media->group => [
                        $this->settings['width'],
                        $this->settings['height'],
                        $this->settings['label'] ?? ucfirst($this->media->group) // Include label if available
                    ]
                ];
            }
        }
        // Original handling for cropable array
        elseif (array_key_exists('cropable', $this->settings)) {
            $cropable = $this->settings['cropable'];

            if (is_array($cropable) && !isset($cropable[0])) {
                // Associative array like ['thumbnail' => [200, 200]] or ['thumbnail' => [200, 200, 'Thumbnail Label']]
                $this->cropable_settings = $cropable;
            } elseif (is_array($cropable) && isset($cropable[0])) {
                // Simple array like [200, 200] or [200, 200, 'Label']
                $this->cropable_settings = ['cropped' => $cropable];
            } elseif ($cropable === true && isset($this->settings['width']) && isset($this->settings['height'])) {
                // Boolean true with dimensions in parent settings
                $this->cropable_settings = [
                    'cropped' => [
                        $this->settings['width'],
                        $this->settings['height'],
                        $this->settings['label'] ?? 'Cropped' // Include label if available
                    ]
                ];
            }
        }

        // Handle route parameters
        if (Route::currentRouteName() == 'mediaclass.cropable') {
            $cropKey = request('crop_key', 'cropped');
            $this->current_crop_key = $cropKey;

            if (isset($this->cropable_settings[$cropKey])) {
                $this->cropable_width  = (int)$this->cropable_settings[$cropKey][0];
                $this->cropable_height = (int)$this->cropable_settings[$cropKey][1];
            } else {
                $this->cropable_width  = (int)request('w');
                $this->cropable_height = (int)request('h');
            }
        }

        return $this;
    }

    public static function form(Media $media)
    {
        // For ghost models, ensure we have a model instance
        if ($media->model_id === null && !$media->relationLoaded('model')) {
            $modelClass = $media->model_type;

            // Handle morph map
            $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
            if (!empty($morphMap)) {
                $modelClass = $morphMap[$modelClass] ?? $modelClass;
            }

            if (class_exists($modelClass)) {
                $model = new $modelClass();
                $media->setRelation('model', $model);
            }
        }

        $cropKey  = request('crop_key', 'cropped');
        $cropable = new Cropable($media);
        $cropable->setCurrentCropKey($cropKey);

        // Pass ghost flag to view
        return view('mediaclass::cropper')->with([
            'media'    => $media,
            'cropable' => $cropable,
            'crop_key' => $cropKey,
            'is_ghost' => $cropable->is_ghost,
        ]);
    }

    /**
     * Get the actual dimensions of the uploaded image
     *
     * @return array|null [width, height] or null if cannot be determined
     */
    private function getActualImageDimensions(): ?array
    {
        try {
            // First check if we have custom group settings - this tells us which file to check
            $groupSettings = Config::getGroupSetings($this->media->model, $this->media->group);

            if (!empty($groupSettings) && isset($groupSettings[$this->media->group])) {
                // For custom group settings, the file is stored with the group's width prefix
                $width = $groupSettings[$this->media->group]['width'];
                $filename = $width . '_' . $this->media->filename . '.' . $this->media->extension();
            } else {
                // For default sizes, get the largest size
                $sizesReversed = Config::getSizesInReverseOrder();
                $largestSizeKey = array_key_first($sizesReversed);

                if ($largestSizeKey) {
                    $width = $sizesReversed[$largestSizeKey]['width'];
                    $filename = $width . '_' . $this->media->filename . '.' . $this->media->extension();
                } else {
                    // Fallback to original file without size prefix
                    $filename = $this->media->filename . '.' . $this->media->extension();
                }
            }

            $path = Path::mediaFolderForMedia($this->media) . '/' . $filename;

            if (Config::getDisk()->exists($path)) {
                $fullPath = Config::getDisk()->path($path);
                if (file_exists($fullPath) && $dimensions = getimagesize($fullPath)) {
                    return [$dimensions[0], $dimensions[1]];
                }
            }

            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if we should show the crop button
     * Returns true if crop button should be shown, false if it should be hidden
     *
     * @param string $cropKey
     * @param int $requiredWidth
     * @param int $requiredHeight
     * @return bool
     */
    private function shouldShowCropButton(string $cropKey, int $requiredWidth, int $requiredHeight): bool
    {
        // Always show if a crop already exists (to allow viewing/deleting)
        if ($this->isCroppedForKey($cropKey)) {
            return true;
        }

        // Get actual image dimensions
        $actualDimensions = $this->getActualImageDimensions();

        if (!$actualDimensions) {
            // If we can't determine dimensions, show the button
            return true;
        }

        list($actualWidth, $actualHeight) = $actualDimensions;

        // Hide button only if actual image dimensions exactly match required dimensions
        if ($actualWidth === $requiredWidth && $actualHeight === $requiredHeight) {
            return false;
        }

        // Show button in all other cases (image is larger, smaller, or different aspect ratio)
        return true;
    }

    /**
     * Updated links() method with proper label handling
     */
    public function links(): string
    {
        if (empty($this->cropable_settings)) {
            return '';
        }

        $buttons = [];

        foreach ($this->cropable_settings as $key => $dimensions) {
            $width  = (int)$dimensions[0];
            $height = (int)$dimensions[1];

            if ($width > 0 && $height > 0) {
                // Check if we should show this crop button
                if (!$this->shouldShowCropButton($key, $width, $height)) {
                    continue;
                }

                // Get label with multiple fallback options
                $label = $this->getCropLabel($key, $dimensions);

                $isCropped  = $this->isCroppedForKey($key);
                $cropClass  = $isCropped ? 'crop cropped' : 'crop';
                $iconClass  = $isCropped ? 'fa-solid fa-crop-simple' : 'fa-solid fa-crop';
                $previewUrl = $isCropped ? $this->media->getCroppedUrl($key) : '';

                $buttons[] = '<a class="'.$cropClass.'"
           data-crop-key="'.$key.'"
           data-crop-label="'.htmlspecialchars($label).'"
           data-crop-w="'.$width.'"
           data-crop-h="'.$height.'"
           data-media-id="'.$this->media->id.'"               
           data-preview-url="'.$previewUrl.'"                
           data-bs-toggle="modal"
           data-bs-target="#mediaclass-crop"
           href="'.route('mediaclass.cropable', $this->media)
                    .'?w='.$width.'&h='.$height.'&crop_key='.$key.'"
           title="'.$label.' ('.$width.'x'.$height.')">
           <i class="'.$iconClass.'"></i>
           <span class="crop-label">'.$label.'</span>
           '.($isCropped ? '<i class="fa-solid fa-circle-check check-icon"></i>' : '').'
         </a>';
            }
        }

        // If no buttons to show, return empty string
        if (empty($buttons)) {
            return '';
        }

        return '<div class="crop-actions-bar">' . implode('', $buttons) . '</div>';
    }

    /**
     * Get the label for a crop configuration
     *
     * @param string $key The crop key
     * @param array $dimensions The dimensions array
     * @return string
     */
    private function getCropLabel(string $key, array $dimensions): string
    {
        // First check if label is in the dimensions array (3rd element)
        if (isset($dimensions[2]) && is_string($dimensions[2])) {
            return $dimensions[2];
        }

        // Then check if we're using a custom group setting with a label
        if ($key === $this->media->group && isset($this->settings['label'])) {
            return $this->settings['label'];
        }

        // Finally, default to capitalized key
        return ucfirst($key);
    }

    public function link(): string
    {
        if (empty($this->cropable_settings)) {
            return '';
        }

        $firstKey = array_key_exists('cropped', $this->cropable_settings)
            ? 'cropped'
            : array_key_first($this->cropable_settings);

        $dimensions = $this->cropable_settings[$firstKey];
        $width      = (int)$dimensions[0];
        $height     = (int)$dimensions[1];

        if ($width <= 0 || $height <= 0) {
            return '';
        }

        $isCropped = $this->isCroppedForKey($firstKey);

        return '<a class="crop"
               data-crop-key="'.$firstKey.'"
               data-crop-w="'.$width.'"
               data-crop-h="'.$height.'"
               data-bs-toggle="modal"
               data-bs-target="#mediaclass-crop"
               href="'.route('mediaclass.cropable', $this->media).'?w='.$width.'&h='.$height.'&crop_key='.$firstKey.'">
                <i class="fa-solid fa-crop"></i>
            </a>';
    }

    public function setCurrentCropKey(string $key): self
    {
        $this->current_crop_key = $key;

        if (isset($this->cropable_settings[$key])) {
            $this->cropable_width  = (int)$this->cropable_settings[$key][0];
            $this->cropable_height = (int)$this->cropable_settings[$key][1];
        }

        return $this;
    }

    public function getCurrentCropKey(): ?string
    {
        return $this->current_crop_key;
    }

    /**
     * Get the label for the current crop key
     *
     * @return string
     */
    public function getCurrentCropLabel(): string
    {
        if ($this->current_crop_key && isset($this->cropable_settings[$this->current_crop_key])) {
            return $this->getCropLabel($this->current_crop_key, $this->cropable_settings[$this->current_crop_key]);
        }

        return ucfirst($this->current_crop_key ?? 'cropped');
    }

    public function setWidth(int $width): self
    {
        $this->cropable_width = $width;

        return $this;
    }

    public function setHeight(int $height): self
    {
        $this->cropable_height = $height;

        return $this;
    }

    public function setCropableFromComponent(array|string|null $cropable): self
    {
        if (!$cropable) {
            return $this;
        }

        if (is_array($cropable)) {
            if (isset($cropable[0]) && isset($cropable[1]) && count($cropable) === 2) {
                $this->cropable_settings = [
                    'cropped' => [
                        (int)$cropable[0],
                        (int)$cropable[1],
                    ],
                ];
                $this->filterEmptyDefaults();
            } else {
                // Associative array - use as is
                $this->cropable_settings = $cropable;
            }
        } else {
            // If it's a string, try to decode as JSON
            $trimmed = trim($cropable, '"');

            if (!$trimmed) {
                return $this;
            }

            // Try to decode as JSON first (for backwards compatibility)
            $decoded = json_decode($trimmed, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Check if decoded array is simple numeric format
                if (isset($decoded[0]) && isset($decoded[1]) && count($decoded) === 2) {
                    $this->cropable_settings = [
                        'cropped' => [
                            (int)$decoded[0],
                            (int)$decoded[1],
                        ],
                    ];
                    $this->filterEmptyDefaults();
                } else {
                    $this->cropable_settings = $decoded;
                }
            } else {
                // Check if it's the [object Object] case
                if ($trimmed === '[object Object]') {
                    throw new \InvalidArgumentException('Mediaclass: Invalid cropable data - object was not properly serialized');
                }

                // Old format - single crop dimensions with comma
                $settings = explode(',', $trimmed);
                if (count($settings) === 2) {
                    $this->cropable_settings = [
                        'cropped' => [
                            (int)current($settings),
                            (int)end($settings),
                        ],
                    ];
                }
                $this->filterEmptyDefaults();
            }
        }

        // Set default dimensions from first crop
        if (!empty($this->cropable_settings)) {
            $firstKey = array_key_first($this->cropable_settings);
            $this->cropable_width = (int)$this->cropable_settings[$firstKey][0];
            $this->cropable_height = (int)$this->cropable_settings[$firstKey][1];
        }

        return $this;
    }

    private function filterEmptyDefaults(): void
    {
        if (!array_filter($this->cropable_settings['cropped'])) {
            $this->cropable_settings = [];
        }
    }

    public function width(): int
    {
        return $this->cropable_width;
    }

    public function height(): int
    {
        return $this->cropable_height;
    }

    public function checkIfCropped(): self
    {
        $this->croppedImages = $this->media->getCroppedImages();

        return $this;
    }

    public function isCroppedForKey(string $key): bool
    {
        return $this->media->isCroppedForKey($key);
    }

    public function isCropable(): bool
    {
        return ! empty($this->cropable_settings);
    }

    public function printSizes(): string
    {
        if (empty($this->cropable_settings)) {
            return '';
        }

        $html = '<div class="crop-sizes">';

        foreach ($this->cropable_settings as $key => $dimensions) {
            $width     = (int)$dimensions[0];
            $height    = (int)$dimensions[1];
            $label     = $this->getCropLabel($key, $dimensions);
            $isCropped = $this->isCroppedForKey($key);

            if ($width > 0 && $height > 0) {
                $html .= '<span class="crop-size-item">';
                $html .= $label.': '.$width.' x '.$height;
                if ($isCropped) {
                    $html .= ' <i class="fa-solid fa-circle-check"></i>';
                }
                $html .= '</span>';
            }
        }

        $html .= '</div>';

        return $html;
    }

    public function printCheckMark(): string
    {
        $hasAnyCrop = false;

        foreach ($this->cropable_settings as $key => $dimensions) {
            if ($this->isCroppedForKey($key)) {
                $hasAnyCrop = true;
                break;
            }
        }

        if ($hasAnyCrop) {
            return '<i class="fa-solid fa-circle-check"></i>';
        }

        return '';
    }

    public function getCropableSettings(): array
    {
        return $this->cropable_settings;
    }

    /**
     * Check if this is the old (backwards compatible) cropable implementation
     */
    public function isCropped(): bool
    {
        // For backwards compatibility, check if any crop exists
        return ! empty($this->croppedImages);
    }

    public function isGhost(): bool
    {
        return $this->is_ghost;
    }
}
