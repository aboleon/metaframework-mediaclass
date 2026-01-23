<?php

namespace MetaFramework\Mediaclass;

use MetaFramework\Mediaclass\Models\Media;

/**
 * Parser class for transforming Media models into convenient data objects
 *
 * This class provides read-only access to media properties and generates
 * URLs for different size variations of images.
 */
class Parser
{
    /**
     * Media ID
     */
    public readonly int $id;

    /**
     * Media group identifier
     */
    public readonly string $group;

    /**
     * Media position (left, right, up, down)
     */
    public readonly string $position;

    /**
     * Localized description
     */
    public readonly ?string $description;

    /**
     * Array of URLs for different sizes
     */
    public readonly array $urls;

    /**
     * Default URL (typically the largest size or cropped if available)
     */
    public readonly string $url;

    /**
     * MIME type of the media
     */
    public readonly string $mime;

    /**
     * Original filename
     */
    public readonly string $filename;

    /**
     * File extension
     */
    public readonly string $extension;

    /**
     * Whether this media is an image
     */
    public readonly bool $isImage;

    /**
     * Whether this media is a file (non-image)
     */
    public readonly bool $isFile;

    /**
     * Whether this media has been cropped
     */
    public readonly bool $hasCropped;

    /**
     * The original Media model instance
     */
    protected readonly Media $media;

    /**
     * Create a new Parser instance
     *
     * @param Media $media The Media model to parse
     * @param array $sizes Optional custom sizes to generate URLs for
     */
    public function __construct(Media $media, array $sizes = [])
    {
        $this->media = $media;

        // Parse basic properties
        $this->id = $media->id;
        $this->mime = $media->mime;
        $this->group = $media->group;
        $this->position = $media->position;
        $this->filename = $media->original_filename;
        $this->extension = $media->extension() ?? '';

        // Parse localized description
        $this->description = $this->parseDescription($media);

        // Determine media type
        $this->isImage = str_contains($media->mime, 'image');
        $this->isFile = !$this->isImage;

        // Generate URLs
        $this->urls = $this->parseUrls($media, $sizes);
        $this->hasCropped = $this->hasAnyCroppedVersion();
        $this->url = $this->getDefaultUrl();
    }

    /**
     * Parse the localized description
     *
     * @param Media $media
     * @return string|null
     */
    protected function parseDescription(Media $media): ?string
    {
        $description = $media->description;

        // If description is null or empty, return empty string
        if (!$description) {
            return '';
        }

        // If it's already a string, return it
        if (is_string($description)) {
            return $description;
        }

        // If it's an array, get the current locale's value
        if (is_array($description)) {
            $locale = app()->getLocale();

            // Try to get current locale's description
            if (isset($description[$locale])) {
                return $description[$locale] ?: '';
            }

            // Fallback: try to get first non-empty value
            foreach ($description as $value) {
                if (!empty($value)) {
                    return $value;
                }
            }

            // If all values are empty, return empty string
            return '';
        }

        // For any other type, cast to string
        return (string) $description;
    }

    /**
     * Parse URLs for different image sizes
     *
     * @param Media $media
     * @param array $customSizes
     * @return array
     */
    protected function parseUrls(Media $media, array $customSizes = []): array
    {
        $sizes = $customSizes ?: Config::getSizes();
        $urls = [];

        // Add standard size URLs first
        foreach (array_keys($sizes) as $size) {
            $file = $this->getFilePath($media, $size);

            if (Config::getDisk()->exists($file)) {
                $urls[$size] = Config::getDisk()->url($file);
            }
        }

        // Check for cropped versions
        $this->addCroppedUrls($media, $urls);

        return $urls;
    }

    /**
     * Add cropped URLs to the urls array
     *
     * @param Media $media
     * @param array &$urls
     */
    protected function addCroppedUrls(Media $media, array &$urls): void
    {
        $folder = Path::mediaFolderName($media->model);
        $filename = $media->filename;
        $extension = $media->extension();
        $group = $media->group; // The media's group is the key

        // Get media settings
        $settings = $media->settings();

        // Check if cropable is configured
        if (!isset($settings['cropable'])) {
            return;
        }

        // Handle different cropable configurations
        if ($settings['cropable'] === true) {
            // When cropable is simply true, use the group as the crop key
            // Format: cropped_{group}_{filename}.{extension}
            $cropFile = "{$folder}/cropped_{$group}_{$filename}.{$extension}";
            if (Config::getDisk()->exists($cropFile)) {
                $urls['cropped'] = Config::getDisk()->url($cropFile);
            }
        } elseif (is_array($settings['cropable'])) {
            // Check if it's a simple dimensions array [width, height]
            if (isset($settings['cropable'][0]) && isset($settings['cropable'][1])) {
                // Simple array format - use group as the crop key
                $cropFile = "{$folder}/cropped_{$group}_{$filename}.{$extension}";
                if (Config::getDisk()->exists($cropFile)) {
                    $urls['cropped'] = Config::getDisk()->url($cropFile);
                }
            } else {
                // Associative array format ['key' => [width, height]]
                // Each key in the array becomes a crop variation
                foreach ($settings['cropable'] as $cropKey => $dimensions) {
                    $cropFile = "{$folder}/cropped_{$cropKey}_{$filename}.{$extension}";
                    if (Config::getDisk()->exists($cropFile)) {
                        $urls["cropped_{$cropKey}"] = Config::getDisk()->url($cropFile);
                    }
                }
            }
        }
    }

    /**
     * Get the file path for a specific size
     *
     * @param Media $media
     * @param string $size
     * @return string
     */
    protected function getFilePath(Media $media, string $size): string
    {
        $folder = Path::mediaFolderName($media->model);
        $prefix = $media->dimensionPrefix($size);
        $filename = $media->filename;
        $extension = $media->extension();

        return "{$folder}/{$prefix}{$filename}.{$extension}";
    }

    /**
     * Get the default URL (prioritizing cropped versions)
     *
     * @return string
     */
    protected function getDefaultUrl(): string
    {
        if (empty($this->urls)) {
            return '';
        }

        // Priority order:
        // 1. Cropped version (if exists)
        if (isset($this->urls['cropped'])) {
            return $this->urls['cropped'];
        }

        // 2. Check for crop variations
        foreach ($this->urls as $key => $url) {
            if (str_starts_with($key, 'cropped_')) {
                return $url;
            }
        }

        // 3. For cropable media without crops yet, return largest size
        $settings = $this->media->settings();
        if (isset($settings['cropable']) && $settings['cropable'] === true) {
            // If it's cropable but no crop exists yet, use xl or largest
            if (isset($this->urls['xl'])) {
                return $this->urls['xl'];
            }
        }

        // 4. Prefer 'xl' size if available
        if (isset($this->urls['xl'])) {
            return $this->urls['xl'];
        }

        // 5. Otherwise return the last (typically largest) URL
        return end($this->urls);
    }

    /**
     * Check if any cropped version exists
     *
     * @return bool
     */
    protected function hasAnyCroppedVersion(): bool
    {
        if (isset($this->urls['cropped'])) {
            return true;
        }

        foreach ($this->urls as $key => $url) {
            if (str_starts_with($key, 'cropped_')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a specific size URL
     *
     * @param string $size
     * @return string|null
     */
    public function getUrl(string $size): ?string
    {
        return $this->urls[$size] ?? null;
    }

    /**
     * Check if a specific size exists
     *
     * @param string $size
     * @return bool
     */
    public function hasSize(string $size): bool
    {
        return isset($this->urls[$size]);
    }

    /**
     * Get all available sizes
     *
     * @return array
     */
    public function getAvailableSizes(): array
    {
        return array_keys($this->urls);
    }

    /**
     * Check if the media has been cropped
     *
     * @param string|null $key Specific crop key to check
     * @return bool
     */
    public function isCropped(?string $key = null): bool
    {
        if ($key === null) {
            return $this->hasCropped;
        }

        return $this->hasSize("cropped_{$key}");
    }

    /**
     * Get the original Media model
     *
     * @return Media
     */
    public function getMedia(): Media
    {
        return $this->media;
    }

    /**
     * Convert to array representation
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'group' => $this->group,
            'position' => $this->position,
            'description' => $this->description,
            'urls' => $this->urls,
            'url' => $this->url,
            'mime' => $this->mime,
            'filename' => $this->filename,
            'extension' => $this->extension,
            'isImage' => $this->isImage,
            'isFile' => $this->isFile,
            'hasCropped' => $this->hasCropped,
        ];
    }

    /**
     * Magic method to get properties
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        // Allow access to underlying Media model properties
        if (property_exists($this->media, $name)) {
            return $this->media->$name;
        }

        throw new \InvalidArgumentException("Property {$name} does not exist on Parser");
    }
}
