<?php

namespace MetaFramework\Mediaclass\Components;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\View\Component;
use MetaFramework\Mediaclass\Support\Config;
use MetaFramework\Mediaclass\Parser;
use MetaFramework\Mediaclass\Printer as MediaPrinter;

/**
 * Blade component for rendering media images
 *
 * This component provides a convenient way to display media in Blade views
 * with support for all Printer features through component attributes.
 */
class Printer extends Component
{
    /**
     * The HTML output to render
     */
    public string $html = '';

    /**
     * The Printer instance used internally
     */
    protected ?MediaPrinter $printer = null;

    /**
     * Allowed output types
     */
    protected array $allowedTypes = ['img', 'url', 'picture'];

    /**
     * Create a new component instance
     *
     * @param mixed $model Parser instance, Media model, or null
     * @param string $size Image size (xs, sm, md, lg, xl, cropped)
     * @param string $type Output type (img, url, picture)
     * @param string|null $class CSS class for the image
     * @param string|null $alt Alt text for the image
     * @param array $params Additional HTML attributes
     * @param string|bool $default Default image URL or true to use system default
     * @param bool $responsive Whether to generate responsive image attributes
     * @param array $breakpoints Custom breakpoints for picture element
     * @param string|null $loading Loading attribute (lazy, eager, auto)
     * @param string|null $id HTML id attribute
     * @param array $data Data attributes (will be prefixed with data-)
     * @param bool $preferCropped Whether to prefer cropped version if available
     * @param string|null $cropKey Specific crop key to use (e.g., 'banner', 'thumbnail')
     */
    public function __construct(
        public mixed $model = null,
        public string $size = 'sm',
        public string $type = 'img',
        public ?string $class = null,
        public ?string $alt = null,
        public array $params = [],
        public string|bool $default = true,
        public bool $responsive = true,
        public array $breakpoints = [],
        public ?string $loading = null,
        public ?string $id = null,
        public array $data = [],
        public bool $preferCropped = true,
        public ?string $cropKey = null,
    ) {
        $this->processInput();
    }

    /**
     * Process the input and generate output
     */
    protected function processInput(): void
    {
        // Handle Parser instance
        if ($this->model instanceof Parser) {
            $this->generateWithParser();
            return;
        }

        // Handle null or invalid model - show default if enabled
        if (!$this->model || !$this->isValidType()) {
            $this->generateDefault();
            return;
        }
    }

    /**
     * Generate output using a Parser instance
     */
    protected function generateWithParser(): void
    {
        $this->printer = new MediaPrinter($this->model);

        // Configure the printer
        $this->configurePrinter();

        // Determine the actual size to use (considering cropped versions)
        $targetSize = $this->determineTargetSize();

        // Generate output based on type
        $this->html = match($this->type) {
            'url' => $this->printer->url($targetSize),
            'picture' => $this->generatePictureElement(),
            default => $this->printer->img($targetSize),
        };
    }

    /**
     * Determine the target size, prioritizing cropped versions if available
     */
    protected function determineTargetSize(): string
    {
        // If we should prefer cropped and no specific crop key is set
        if ($this->preferCropped && !$this->cropKey) {
            // Check if default cropped version exists
            if ($this->model->isCropped()) {
                return 'cropped';
            }
        }

        // If a specific crop key is requested
        if ($this->cropKey) {
            // Check if this specific crop exists
            if ($this->model->isCropped($this->cropKey)) {
                return "cropped_{$this->cropKey}";
            }
        }

        // Otherwise use the requested size
        return $this->size;
    }

    /**
     * Generate picture element with crop-aware breakpoints
     */
    protected function generatePictureElement(): string
    {
        // If custom breakpoints provided, use them
        if (!empty($this->breakpoints)) {
            return $this->printer->picture($this->breakpoints);
        }

        // Generate smart breakpoints considering available crops
        $smartBreakpoints = $this->generateSmartBreakpoints();

        return $this->printer->picture($smartBreakpoints);
    }

    /**
     * Generate breakpoints that consider available crops
     */
    protected function generateSmartBreakpoints(): array
    {
        $breakpoints = [];

        // Check for mobile-specific crops
        if ($this->model->isCropped('mobile')) {
            $breakpoints['max-width: 640px'] = 'cropped_mobile';
        } else {
            $breakpoints['max-width: 640px'] = 'sm';
        }

        // Check for tablet-specific crops
        if ($this->model->isCropped('tablet')) {
            $breakpoints['max-width: 1024px'] = 'cropped_tablet';
        } else {
            $breakpoints['max-width: 768px'] = 'md';
            $breakpoints['max-width: 1024px'] = 'lg';
        }

        // For desktop, prefer cropped version if available
        if ($this->preferCropped && $this->model->isCropped()) {
            $breakpoints['min-width: 1025px'] = 'cropped';
        } else {
            $breakpoints['min-width: 1025px'] = 'xl';
        }

        return $breakpoints;
    }

    /**
     * Configure the Printer instance with component attributes
     */
    protected function configurePrinter(): void
    {
        if (!$this->printer) {
            return;
        }

        // Set size
        $this->printer->setSize($this->size);

        // Set responsive
        if (!$this->responsive) {
            $this->printer->disableResponsive();
        }

        // Handle default image
        if ($this->default === false) {
            $this->printer->noDefault();
        } elseif (is_string($this->default) && $this->default !== 'true') {
            $this->printer->setDefaultUrl($this->default);
        }

        // Build and set attributes
        $attributes = $this->buildAttributes();
        if (!empty($attributes)) {
            $this->printer->setAttributes($attributes);
        }
    }

    /**
     * Build attributes array from component properties
     */
    protected function buildAttributes(): array
    {
        $attrs = $this->params;

        // Add specific attributes
        if ($this->class !== null) {
            $attrs['class'] = $this->class;
        }

        if ($this->alt !== null) {
            $attrs['alt'] = $this->alt;
        }

        if ($this->loading !== null) {
            $attrs['loading'] = $this->loading;
        }

        if ($this->id !== null) {
            $attrs['id'] = $this->id;
        }

        // Add data attributes
        foreach ($this->data as $key => $value) {
            $attrs["data-{$key}"] = $value;
        }

        return $attrs;
    }

    /**
     * Generate default output when no valid model is provided
     */
    protected function generateDefault(): void
    {
        if (!$this->default) {
            $this->html = '';
            return;
        }

        $defaultUrl = is_string($this->default) && $this->default !== 'true'
            ? $this->default
            : Config::defaultImgUrl();

        if ($this->type === 'url') {
            $this->html = $defaultUrl;
        } else {
            $this->html = $this->generateDefaultImg($defaultUrl);
        }
    }

    /**
     * Generate default img tag
     */
    protected function generateDefaultImg(string $url): string
    {
        $attributes = $this->buildAttributes();

        // Ensure alt attribute
        if (!isset($attributes['alt'])) {
            $attributes['alt'] = config('app.name', '');
        }

        // Add src
        $attributes['src'] = $url;

        // Build HTML
        $parts = [];
        foreach ($attributes as $key => $value) {
            if ($value !== null && $value !== false) {
                $parts[] = sprintf('%s="%s"', $key, htmlspecialchars($value));
            }
        }

        return sprintf('<img %s />', implode(' ', $parts));
    }

    /**
     * Check if the output type is valid
     */
    protected function isValidType(): bool
    {
        return in_array($this->type, $this->allowedTypes);
    }

    /**
     * Get the view / contents that represent the component
     */
    public function render(): Renderable
    {
        return view('mediaclass::components.printer')->with('html', $this->html);
    }

    /**
     * Get the generated HTML
     * This method is used by the view
     */
    public function output(): string
    {
        return $this->html;
    }
}