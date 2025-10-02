<?php

namespace MetaFramework\Mediaclass;

/**
 * Printer class for rendering media as HTML elements
 *
 * This class provides a fluent interface for generating img tags and URLs
 * with support for responsive images, custom attributes, and size variations.
 */
class Printer
{
    /**
     * The Parser instance containing media data
     */
    protected Parser $media;

    /**
     * Default image URL when media is not available
     */
    protected string $defaultImgUrl;

    /**
     * Whether to use the default image when media is not available
     */
    protected bool $useDefault = true;

    /**
     * HTML attributes for the img tag
     */
    protected array $attributes = [];

    /**
     * Whether to generate responsive srcset and sizes attributes
     */
    protected bool $responsive = true;

    /**
     * The selected image size
     */
    protected string $size = 'sm';

    /**
     * Available image sizes from configuration
     */
    protected array $availableSizes;

    /**
     * Create a new Printer instance
     *
     * @param Parser $media The parsed media data
     * @param string $size Initial size selection
     */
    public function __construct(Parser $media, string $size = 'sm')
    {
        $this->media = $media;
        $this->size = $size;
        $this->defaultImgUrl = Config::defaultImgUrl();
        $this->availableSizes = Config::getSizes();
    }

    /**
     * Get the URL for the current or specified size
     *
     * @param string|null $size Optional size override
     * @return string
     */
    public function url(?string $size = null): string
    {
        $targetSize = $size ?? $this->size;

        // Try to get the URL for the requested size
        $url = $this->media->getUrl($targetSize);

        if ($url) {
            return $url;
        }

        // Fallback to the default URL from media
        if ($this->media->url) {
            return $this->media->url;
        }

        // Return default image if enabled
        return $this->useDefault ? $this->defaultImgUrl : '';
    }

    /**
     * Generate an img tag for the media
     *
     * @param string|null $size Optional size override
     * @return string
     */
    public function img(?string $size = null): string
    {
        if ($size) {
            $this->setSize($size);
        }

        $url = $this->url();

        if (!$url) {
            return '';
        }

        $attributes = $this->buildAttributes($url);

        return sprintf('<img %s />', $attributes);
    }

    /**
     * Generate a picture element with multiple sources
     *
     * @param array $breakpoints Associative array of breakpoint => size
     * @return string
     */
    public function picture(array $breakpoints = []): string
    {
        if (empty($breakpoints)) {
            $breakpoints = $this->getDefaultBreakpoints();
        }

        $html = '<picture>';

        // Add source elements for each breakpoint
        foreach ($breakpoints as $media => $size) {
            if ($url = $this->media->getUrl($size)) {
                $html .= sprintf(
                    '<source media="(%s)" srcset="%s">',
                    $media,
                    $url
                );
            }
        }

        // Add the img element as fallback
        $html .= $this->img();
        $html .= '</picture>';

        return $html;
    }

    /**
     * Set the image size
     *
     * @param string $size
     * @return static
     */
    public function setSize(string $size): static
    {
        if (array_key_exists($size, $this->availableSizes) || $size === 'cropped') {
            $this->size = $size;
        }

        return $this;
    }

    /**
     * Get the current size
     *
     * @return string
     */
    public function getSize(): string
    {
        return $this->size;
    }

    /**
     * Disable responsive image generation
     *
     * @return static
     */
    public function disableResponsive(): static
    {
        $this->responsive = false;
        return $this;
    }

    /**
     * Enable responsive image generation
     *
     * @return static
     */
    public function enableResponsive(): static
    {
        $this->responsive = true;
        return $this;
    }

    /**
     * Disable the use of default image
     *
     * @return static
     */
    public function noDefault(): static
    {
        $this->useDefault = false;
        return $this;
    }

    /**
     * Enable the use of default image
     *
     * @return static
     */
    public function withDefault(): static
    {
        $this->useDefault = true;
        return $this;
    }

    /**
     * Set custom default image URL
     *
     * @param string $url
     * @return static
     */
    public function setDefaultUrl(string $url): static
    {
        $this->defaultImgUrl = $url;
        return $this;
    }

    /**
     * Set HTML attributes
     *
     * @param array|string $key Attribute name or array of attributes
     * @param mixed $value Attribute value (if key is string)
     * @return static
     */
    public function setAttributes(array|string $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->attributes = array_merge($this->attributes, $key);
        } else {
            $this->attributes[$key] = $value;
        }

        return $this;
    }

    /**
     * Alias for setAttributes for backward compatibility
     *
     * @param array|string $key
     * @param mixed $value
     * @param bool $reset
     * @return static
     */
    public function setParams(array|string $key, mixed $value = null, bool $reset = false): static
    {
        if ($reset) {
            $this->attributes = [];
        }

        return $this->setAttributes($key, $value);
    }

    /**
     * Set CSS class attribute
     *
     * @param string $class
     * @return static
     */
    public function setClass(string $class): static
    {
        return $this->setAttributes('class', $class);
    }

    /**
     * Add CSS class to existing classes
     *
     * @param string $class
     * @return static
     */
    public function addClass(string $class): static
    {
        $existing = $this->attributes['class'] ?? '';
        $classes = array_filter(array_merge(
            explode(' ', $existing),
            explode(' ', $class)
        ));

        return $this->setAttributes('class', implode(' ', array_unique($classes)));
    }

    /**
     * Set alt attribute
     *
     * @param string $alt
     * @return static
     */
    public function setAlt(string $alt): static
    {
        return $this->setAttributes('alt', $alt);
    }

    /**
     * Set loading attribute (lazy, eager, auto)
     *
     * @param string $loading
     * @return static
     */
    public function setLoading(string $loading = 'lazy'): static
    {
        return $this->setAttributes('loading', $loading);
    }

    /**
     * Build the HTML attributes string
     *
     * @param string $url
     * @return string
     */
    protected function buildAttributes(string $url): string
    {
        $attributes = $this->attributes;

        // Set src
        $attributes['src'] = $url;

        // Set loading if not set
        if (!isset($attributes['loading'])) {
            $attributes['loading'] = 'lazy';
        }

        // Set alt if not set
        if (!isset($attributes['alt'])) {
            $attributes['alt'] = $this->media->description ?: config('app.name', '');
        }

        // Add responsive attributes if enabled
        if ($this->responsive && $this->media->isImage) {
            $responsive = $this->generateResponsiveAttributes();
            if ($responsive) {
                $attributes = array_merge($attributes, $responsive);
            }
        }

        // Build attribute string
        $parts = [];
        foreach ($attributes as $key => $value) {
            if ($value !== null && $value !== false) {
                $parts[] = sprintf('%s="%s"', $key, htmlspecialchars($value));
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Generate srcset and sizes attributes for responsive images
     *
     * @return array
     */
    protected function generateResponsiveAttributes(): array
    {
        $srcset = [];
        $sizes = [];

        foreach ($this->availableSizes as $key => $dimensions) {
            if ($url = $this->media->getUrl($key)) {
                $width = $dimensions['width'];
                $srcset[] = "{$url} {$width}w";

                // Generate sizes attribute
                if ($width != Config::getMaxSize()) {
                    $sizes[] = "(max-width: {$width}px) {$width}px";
                }
            }
        }

        if (empty($srcset)) {
            return [];
        }

        // Add default size
        $sizes[] = Config::getMaxSize() . 'px';

        return [
            'srcset' => implode(', ', $srcset),
            'sizes' => implode(', ', array_reverse($sizes)),
        ];
    }

    /**
     * Get default breakpoints for picture element
     *
     * @return array
     */
    protected function getDefaultBreakpoints(): array
    {
        return [
            'max-width: 640px' => 'sm',
            'max-width: 768px' => 'md',
            'max-width: 1024px' => 'lg',
            'min-width: 1025px' => 'xl',
        ];
    }

    /**
     * Check if the printer has valid media
     *
     * @return bool
     */
    public function hasMedia(): bool
    {
        return !empty($this->media->urls);
    }

    /**
     * Get the Parser instance
     *
     * @return Parser
     */
    public function getMedia(): Parser
    {
        return $this->media;
    }
}