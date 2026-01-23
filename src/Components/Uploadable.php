<?php

namespace MetaFramework\Mediaclass\Components;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\View\Component;

class Uploadable extends Component
{
    public ?int $requiredWidth = null;
    public ?int $requiredHeight = null;
    public string $displayLabel = '';

    public function __construct(
        public object $model,
        public bool   $positions = false,
        public string $group = 'media',
        public string $size = '',
        public string $label = 'Médias',
        public int|bool $description = true,
        public array|string|null $cropable = null,
        /**
         * @var int
         * max number of files to be uploaded
         */
        public int $limit = 0,
        /**
         * @var string|null
         * ex 500KB, 5MB (default is 16MB)
         */
        public ?string $maxfilesize = null,
        public array $settings = [],
        public array $storables = [],
        public string $icon = 'bi bi-card-image',
        public string $nomedia = '',
        public bool $ghost = false,
        /**
         * @var string|null
         * JavaScript callback function name to call after successful upload
         * The function will receive the upload response data as parameter
         */
        public ?string $callback = null
    )
    {
        $this->group = $this->settings['group'] ?? $this->group;
        $this->label = $this->settings['label'] ?? $this->label;
        $this->description = $this->description ? 1 : 0;
        $this->nomedia = $this->nomedia ?: __('mfw-mediaclass.no_media');

        // Check if model has mediaclassSettings for this group
        if ($this->cropable === null && method_exists($this->model, 'mediaclassSettings')) {
            $modelSettings = $this->model->mediaclassSettings();

            if (isset($modelSettings[$this->group])) {
                $groupSettings = $modelSettings[$this->group];

                // Set label from group settings if available
                if (isset($groupSettings['label'])) {
                    $this->label = $groupSettings['label'];
                }

                // Extract dimensions
                if (isset($groupSettings['width']) && isset($groupSettings['height'])) {
                    $this->requiredWidth = $groupSettings['width'];
                    $this->requiredHeight = $groupSettings['height'];
                }

                // Set cropable if defined in group settings
                // Note: cropable will be dynamically determined based on uploaded image dimensions
                // This is just a fallback for the component initialization
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

        // Extract dimensions from settings array if available
        if (!$this->requiredWidth && !$this->requiredHeight && isset($this->settings['sizes']) && is_array($this->settings['sizes'])) {
            $this->requiredWidth = (int) $this->settings['sizes'][0];
            $this->requiredHeight = (int) $this->settings['sizes'][1];
        }

        // Build display label with dimensions
        $this->displayLabel = $this->label;
        /* if ($this->requiredWidth && $this->requiredHeight) {
             $this->displayLabel .= ' <span class="dimensions-info" style="background: rgba(0,0,0,0.1); padding: 2px 8px; border-radius: 4px;">(' . $this->requiredWidth . ' × ' . $this->requiredHeight . ' px)</span>';
         }*/
        if (is_array($this->cropable)) {
            $this->cropable = json_encode($this->cropable);
        }
    }

    public function render(): Renderable
    {
        return view('mediaclass::components.uploadable');
    }
}
