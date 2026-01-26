<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass;

use Illuminate\Support\HtmlString;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Support\Config;

/**
 * Fluent builder for media URLs and HTML rendering.
 *
 * Provides a Laravel-style chainable API for working with media.
 *
 * @example
 * // Get URL
 * $post->media('cover')->url();
 * $post->media('cover')->url('lg');
 *
 * // Get img tag
 * $post->media('cover')->class('rounded')->alt('Photo')->img();
 *
 * // Get cropped version
 * $post->media('cover')->crop('banner');
 */
class MediaBuilder
{
    protected ?Media $media = null;
    protected string $size = 'sm';
    protected array $attributes = [];
    protected ?string $defaultUrl = null;
    protected bool $useDefault = true;
    protected bool $preferCropped = true;
    protected ?string $cropKey = null;

    public function __construct(?Media $media = null)
    {
        $this->media = $media;
        $this->defaultUrl = Config::defaultImgUrl();
    }

    /**
     * Set the media instance.
     */
    public function setMedia(?Media $media): static
    {
        $this->media = $media;
        return $this;
    }

    /**
     * Get the underlying Media model.
     */
    public function getMedia(): ?Media
    {
        return $this->media;
    }

    /**
     * Check if media exists.
     */
    public function exists(): bool
    {
        return $this->media !== null;
    }

    // -------------------------------------------------------------------------
    // Size & Crop Methods
    // -------------------------------------------------------------------------

    /**
     * Set the image size.
     */
    public function size(string $size): static
    {
        $this->size = $size;
        return $this;
    }

    /**
     * Shorthand for common sizes.
     */
    public function sm(): static { return $this->size('sm'); }
    public function md(): static { return $this->size('md'); }
    public function lg(): static { return $this->size('lg'); }
    public function xl(): static { return $this->size('xl'); }

    /**
     * Get cropped version by key.
     */
    public function crop(?string $key = null): static
    {
        $this->cropKey = $key;
        $this->preferCropped = true;
        return $this;
    }

    /**
     * Check if a crop exists.
     */
    public function hasCrop(?string $key = null): bool
    {
        if (!$this->media) {
            return false;
        }

        return $key ? $this->media->isCroppedForKey($key) : $this->media->isCropped();
    }

    /**
     * Get available sizes.
     */
    public function sizes(): array
    {
        if (!$this->media) {
            return [];
        }

        return array_keys($this->buildUrls());
    }

    // -------------------------------------------------------------------------
    // HTML Attribute Methods (Fluent)
    // -------------------------------------------------------------------------

    /**
     * Set CSS class(es).
     */
    public function class(string $class): static
    {
        $this->attributes['class'] = $class;
        return $this;
    }

    /**
     * Add CSS class(es) to existing.
     */
    public function addClass(string $class): static
    {
        $existing = $this->attributes['class'] ?? '';
        $this->attributes['class'] = trim($existing . ' ' . $class);
        return $this;
    }

    /**
     * Set alt text.
     */
    public function alt(string $alt): static
    {
        $this->attributes['alt'] = $alt;
        return $this;
    }

    /**
     * Set ID attribute.
     */
    public function id(string $id): static
    {
        $this->attributes['id'] = $id;
        return $this;
    }

    /**
     * Set loading attribute to lazy.
     */
    public function lazy(): static
    {
        $this->attributes['loading'] = 'lazy';
        return $this;
    }

    /**
     * Set loading attribute to eager.
     */
    public function eager(): static
    {
        $this->attributes['loading'] = 'eager';
        return $this;
    }

    /**
     * Set width attribute.
     */
    public function width(int $width): static
    {
        $this->attributes['width'] = $width;
        return $this;
    }

    /**
     * Set height attribute.
     */
    public function height(int $height): static
    {
        $this->attributes['height'] = $height;
        return $this;
    }

    /**
     * Set any attribute.
     */
    public function attr(string $name, mixed $value): static
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    /**
     * Set multiple attributes at once.
     */
    public function attrs(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    /**
     * Set data-* attributes.
     */
    public function data(string $key, mixed $value): static
    {
        $this->attributes["data-{$key}"] = $value;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Default/Fallback Methods
    // -------------------------------------------------------------------------

    /**
     * Set default URL when no media exists.
     */
    public function default(?string $url): static
    {
        $this->defaultUrl = $url;
        $this->useDefault = $url !== null;
        return $this;
    }

    /**
     * Disable default fallback.
     */
    public function noDefault(): static
    {
        $this->useDefault = false;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Output Methods
    // -------------------------------------------------------------------------

    /**
     * Get the image URL.
     */
    public function url(?string $size = null): string
    {
        if ($size) {
            $this->size = $size;
        }

        if (!$this->media) {
            return $this->useDefault ? ($this->defaultUrl ?? '') : '';
        }

        // Check for cropped version first
        if ($this->cropKey && $this->media->isCroppedForKey($this->cropKey)) {
            return $this->media->getCroppedUrl($this->cropKey) ?: $this->fallbackUrl();
        }

        if ($this->preferCropped && $this->media->isCropped()) {
            $croppedUrl = $this->media->url('cropped');
            if ($croppedUrl) {
                return $croppedUrl;
            }
        }

        return $this->media->url($this->size) ?: $this->fallbackUrl();
    }

    /**
     * Get all available URLs.
     */
    public function urls(): array
    {
        return $this->buildUrls();
    }

    /**
     * Render as img tag.
     */
    public function img(?string $size = null, array $attributes = []): HtmlString
    {
        if ($size) {
            $this->size = $size;
        }

        if ($attributes) {
            $this->attrs($attributes);
        }

        $url = $this->url();
        if (!$url) {
            return new HtmlString('');
        }

        $attrs = $this->buildAttributes();
        $attrsString = $this->attributesToString($attrs);

        return new HtmlString("<img src=\"{$url}\"{$attrsString}>");
    }

    /**
     * Render as picture element with responsive sources.
     */
    public function picture(?array $breakpoints = null, array $attributes = []): HtmlString
    {
        if ($attributes) {
            $this->attrs($attributes);
        }

        $breakpoints = $breakpoints ?? [
            '(max-width: 640px)' => 'sm',
            '(max-width: 1024px)' => 'md',
            '(min-width: 1025px)' => 'xl',
        ];

        if (!$this->media) {
            $fallback = $this->useDefault ? ($this->defaultUrl ?? '') : '';
            if (!$fallback) {
                return new HtmlString('');
            }
            $attrs = $this->buildAttributes();
            $attrsString = $this->attributesToString($attrs);
            return new HtmlString("<picture><img src=\"{$fallback}\"{$attrsString}></picture>");
        }

        $sources = [];
        foreach ($breakpoints as $media => $size) {
            $url = $this->media->url($size);
            if ($url) {
                $sources[] = "<source media=\"{$media}\" srcset=\"{$url}\">";
            }
        }

        $fallbackUrl = $this->url();
        $attrs = $this->buildAttributes();
        $attrsString = $this->attributesToString($attrs);

        $sourcesHtml = implode("\n    ", $sources);

        return new HtmlString("<picture>\n    {$sourcesHtml}\n    <img src=\"{$fallbackUrl}\"{$attrsString}>\n</picture>");
    }

    /**
     * Render as background-image style.
     */
    public function background(?string $size = null): string
    {
        $url = $this->url($size);
        return $url ? "background-image: url('{$url}');" : '';
    }

    /**
     * Convert to string (returns URL).
     */
    public function __toString(): string
    {
        return $this->url();
    }

    // -------------------------------------------------------------------------
    // Protected Helpers
    // -------------------------------------------------------------------------

    protected function fallbackUrl(): string
    {
        return $this->useDefault ? ($this->defaultUrl ?? '') : '';
    }

    protected function buildUrls(): array
    {
        if (!$this->media) {
            return [];
        }

        $urls = [];
        $groupSizes = Config::getGroupSizes($this->media->model, $this->media->group);
        $sizes = !empty($groupSizes) ? $groupSizes : Config::getSizes();

        foreach (array_keys($sizes) as $size) {
            $url = $this->media->url($size);
            if ($url) {
                $urls[$size] = $url;
            }
        }

        // Add cropped versions
        if ($this->media->isCropped()) {
            $croppedUrl = $this->media->url('cropped');
            if ($croppedUrl) {
                $urls['cropped'] = $croppedUrl;
            }
        }

        return $urls;
    }

    protected function buildAttributes(): array
    {
        $attrs = $this->attributes;

        // Set alt from media description if not provided
        if (!isset($attrs['alt']) && $this->media) {
            $description = $this->media->description;
            if (is_array($description)) {
                $attrs['alt'] = $description[app()->getLocale()] ?? '';
            } elseif (is_string($description)) {
                $attrs['alt'] = $description;
            }
        }

        // Ensure alt is always set (even if empty) for accessibility
        if (!isset($attrs['alt'])) {
            $attrs['alt'] = '';
        }

        return $attrs;
    }

    protected function attributesToString(array $attributes): string
    {
        if (empty($attributes)) {
            return '';
        }

        $parts = [];
        foreach ($attributes as $key => $value) {
            if ($value === true) {
                $parts[] = $key;
            } elseif ($value !== false && $value !== null) {
                $parts[] = $key . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
            }
        }

        return $parts ? ' ' . implode(' ', $parts) : '';
    }
}
