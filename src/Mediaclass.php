<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass;

use Cohensive\OEmbed\Factory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use MetaFramework\Mediaclass\Contracts\MediaclassInterface;
use MetaFramework\Mediaclass\Data\ExternalVideoEmbed;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Support\EmbedProviderManager;
use Throwable;

class Mediaclass
{
    protected EmbedProviderManager $embedProviderManager;

    public function __construct(
        protected ?Factory $oEmbedFactory = null,
        ?EmbedProviderManager $embedProviderManager = null,
    ) {
        $this->embedProviderManager = $embedProviderManager ?? EmbedProviderManager::withDefaults();
    }

    /**
     * Selected group for querying Mediaclass database
     */
    protected ?string $selected_group = null;

    /**
     * The MediaclassInterface object to work with
     */
    protected MediaclassInterface $object;

    /**
     * Array of parsed media instances
     */
    protected array $media = [];

    /**
     * The retrieved Media collection from database
     */
    protected Media|EloquentCollection|null $mediaCollection = null;

    /**
     * Flag to determine if only a single media should be returned
     */
    protected bool $single = false;

    /**
     * Associates the current instance with a MediaclassInterface object.
     *
     * @param  MediaclassInterface  $object  The object implementing MediaclassInterface.
     * @return static The current instance for method chaining.
     */
    public function on(MediaclassInterface $object): static
    {
        $this->object = $object;

        return $this;
    }

    /**
     * Sets the group for filtering media.
     *
     * @param  string  $group  The group name for filtering.
     * @return static The current instance for method chaining.
     */
    public function group(string $group): static
    {
        $this->selected_group = $group;

        return $this;
    }

    /**
     * Sets the instance to return only a single media item.
     *
     * @return static The current instance for method chaining.
     */
    public function single(): static
    {
        $this->single = true;

        return $this;
    }

    /**
     * Fetches media from the database based on the associated object and optional group.
     *
     * @return static The current instance for method chaining.
     */
    public function fetch(): static
    {
        if (!isset($this->object)) {
            return $this;
        }

        $this->mediaCollection = $this->selected_group
            ? $this->object->media->where('group', $this->selected_group)
            : $this->object->media;

        $this->selected_group = null; // Reset after use

        return $this;
    }

    /**
     * Parses the fetched media collection into Parser instances.
     *
     * @return static The current instance for method chaining.
     */
    public function parse(): static
    {
        if (!$this->mediaCollection) {
            return $this;
        }

        // Handle single Media instance
        if ($this->mediaCollection instanceof Media) {
            $this->parseMedia($this->mediaCollection);
        }
        // Handle collection of Media instances
        elseif ($this->mediaCollection instanceof EloquentCollection) {
            foreach ($this->mediaCollection as $item) {
                $item->setRelation('model', $this->object);
                $this->parseMedia($item);

                // Break if single mode is enabled
                if ($this->single) {
                    break;
                }
            }
        }

        return $this;
    }

    /**
     * Parses a single Media instance into a Parser object.
     *
     * @param  Media  $instance  The Media instance to parse.
     * @return static The current instance for method chaining.
     */
    protected function parseMedia(Media $instance): static
    {
        // Only set model if we have an object and the media doesn't already have a model relation
        if (isset($this->object) && !$instance->relationLoaded('model')) {
            $instance->setRelation('model', $this->object);
        }

        $this->media[] = new Parser($instance);

        return $this;
    }

    /**
     * Resets the parsed media array.
     *
     * @return static The current instance for method chaining.
     */
    public function reset(): static
    {
        $this->media = [];
        $this->mediaCollection = null;
        $this->selected_group = null;
        $this->single = false;

        return $this;
    }

    /**
     * ------------------------------
     * CONVENIENCE METHODS
     * ------------------------------
     */

    /**
     * Retrieves and parses media for a given model and optional group.
     * This is a convenience method that combines on(), group(), fetch(), and parse().
     *
     * @param  MediaclassInterface|null  $object  The model to get media for.
     * @param  string|null  $group  Optional group filter.
     * @return static The current instance for method chaining.
     */
    public function forModel(?MediaclassInterface $object = null, ?string $group = null): static
    {
        $this->reset();

        if (!$object) {
            return $this;
        }

        if ($group) {
            $this->group($group);
        }

        return $this->on($object)->fetch()->parse();
    }

    /**
     * ------------------------------
     * RETRIEVAL METHODS
     * ------------------------------
     */

    /**
     * Gets the first parsed media item.
     *
     * @return Parser|null The first Parser instance or null if none exist.
     */
    public function first(): ?Parser
    {
        return $this->media[0] ?? null;
    }

    /**
     * Gets all parsed media items.
     *
     * @return array Array of Parser instances.
     */
    public function all(): array
    {
        return $this->media;
    }

    /**
     * Alias for all() method.
     *
     * @return array Array of Parser instances.
     */
    public function toArray(): array
    {
        return $this->all();
    }

    /**
     * Gets the raw media collection from database.
     *
     * @return EloquentCollection The Eloquent collection of Media models.
     */
    public function get(): EloquentCollection
    {
        if ($this->mediaCollection instanceof EloquentCollection) {
            return $this->mediaCollection;
        }

        return new EloquentCollection($this->mediaCollection ? [$this->mediaCollection] : []);
    }

    /**
     * ------------------------------
     * FILTERING METHODS
     * ------------------------------
     */

    /**
     * Filters the media collection by subgroup.
     *
     * @param  string  $identifier  The subgroup identifier.
     * @return EloquentCollection Filtered collection.
     */
    public function forSubGroup(string $identifier): EloquentCollection
    {
        $collection = $this->get();

        return $collection->filter(fn ($item) => $item->subgroup == $identifier);
    }

    /**
     * Gets parsed media for a specific subgroup.
     *
     * @param  string  $identifier  The subgroup identifier.
     * @return Collection Collection of Parser instances.
     */
    public function parsedForSubGroup(string $identifier): Collection
    {
        return $this->forSubGroup($identifier)->map(fn ($item) => new Parser($item));
    }

    /**
     * Filters the media collection by group.
     *
     * @param  string  $identifier  The group identifier.
     * @return EloquentCollection Filtered collection.
     */
    public function forGroup(string $identifier): EloquentCollection
    {
        $collection = $this->get();

        return $collection->filter(fn ($item) => $item->group == $identifier);
    }

    /**
     * Gets parsed media for a specific group.
     *
     * @param  string  $identifier  The group identifier.
     * @return Collection Collection of Parser instances.
     */
    public function parsedForGroup(string $identifier): Collection
    {
        return $this->forGroup($identifier)->map(fn ($item) => new Parser($item));
    }

    /**
     * ------------------------------
     * UTILITY METHODS
     * ------------------------------
     */

    /**
     * Checks if any media exists.
     *
     * @return bool True if media exists, false otherwise.
     */
    public function exists(): bool
    {
        return !empty($this->media);
    }

    /**
     * Counts the number of parsed media items.
     *
     * @return int The count of media items.
     */
    public function count(): int
    {
        return count($this->media);
    }

    /**
     * Checks if the collection is empty.
     *
     * @return bool True if empty, false otherwise.
     */
    public function isEmpty(): bool
    {
        return empty($this->media);
    }

    /**
     * Checks if the collection is not empty.
     *
     * @return bool True if not empty, false otherwise.
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Maps over the parsed media items.
     *
     * @param  callable  $callback  The callback to apply to each item.
     * @return array The mapped results.
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->media);
    }

    /**
     * Filters the parsed media items.
     *
     * @param  callable  $callback  The filter callback.
     * @return array The filtered items.
     */
    public function filter(callable $callback): array
    {
        return array_filter($this->media, $callback);
    }

    /**
     * Render an external media URL through its configured oEmbed provider.
     *
     * @param  array<string, mixed>  $options
     */
    public function embed(Media|string|null $source, array $options = []): HtmlString
    {
        $url = $this->embedUrl($source);

        if ($url === null) {
            return new HtmlString('');
        }

        $options = array_merge(
            $source instanceof Media
                ? $source->embedOptions()
                : [
                    'width' => Media::DEFAULT_EMBED_WIDTH,
                    'height' => Media::DEFAULT_EMBED_HEIGHT,
                ],
            $options,
        );

        $html = $this->externalVideoEmbedHtml($this->embedProviderManager->embed($url), $options);

        if ($html === '') {
            try {
                $embed = $this->oEmbedFactory()->get($url);
                $html = $embed?->html($this->sanitizeEmbedOptions($options)) ?? '';
            } catch (Throwable) {
                $html = '';
            }
        }

        if ($html === '') {
            $html = $this->html5VideoHtml($url, $options);
        }

        return new HtmlString($html);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function externalVideoEmbedHtml(?ExternalVideoEmbed $embed, array $options): string
    {
        if (!$embed instanceof ExternalVideoEmbed || $this->validHttpUrl($embed->src) === null) {
            return '';
        }

        $attributes = $this->sanitizeEmbedOptions(array_merge($embed->attributes, $options));
        $responsiveStyle = $this->responsiveEmbedStyle($embed, $attributes);

        if ($responsiveStyle !== null) {
            unset($attributes['height']);
        } elseif (($attributes['height'] ?? null) === Media::DEFAULT_EMBED_HEIGHT) {
            $attributes['height'] = Media::DEFAULT_EMBED_PIXEL_HEIGHT;
        }

        $attributes = collect($attributes)
            ->map(static function (bool|float|int|string $value, string $key): string {
                if (is_bool($value)) {
                    return $value ? $key : '';
                }

                return $key . '="' . $value . '"';
            })
            ->filter()
            ->implode(' ');

        return '<iframe src="' . htmlspecialchars($embed->src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ($attributes !== '' ? ' ' . $attributes : '')
            . ($responsiveStyle !== null ? ' style="' . $responsiveStyle . '"' : '')
            . '></iframe>';
    }

    /**
     * @param  array<string, bool|float|int|string>  $attributes
     */
    protected function responsiveEmbedStyle(ExternalVideoEmbed $embed, array $attributes): ?string
    {
        if ($embed->aspectRatio === null || !$this->usesResponsiveEmbedHeight($attributes['height'] ?? null)) {
            return null;
        }

        return $this->responsiveEmbedWidthStyle($attributes['width'] ?? null)
            . 'height: auto; aspect-ratio: ' . $embed->aspectRatio->toCssValue() . ';';
    }

    protected function responsiveEmbedWidthStyle(mixed $width): string
    {
        if (is_string($width) && trim($width) === '100%') {
            return 'width: 100%; ';
        }

        $width = filter_var($width, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => 7680,
            ],
        ]);

        return $width !== false ? 'width: ' . $width . 'px; max-width: 100%; ' : '';
    }

    protected function usesResponsiveEmbedHeight(mixed $height): bool
    {
        return $height === null || (is_string($height)
            && in_array(strtolower(trim($height)), [Media::DEFAULT_EMBED_HEIGHT, '100%'], true));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function html5VideoHtml(string $url, array $options): string
    {
        $mimeType = Media::html5VideoMimeTypeForUrl($url);

        if ($mimeType === null) {
            return '';
        }

        $attributes = $this->sanitizeEmbedOptions(['controls' => true, ...$options]);
        $attributes = collect($attributes)
            ->map(static function (bool|float|int|string $value, string $key): string {
                if (is_bool($value)) {
                    return $value ? $key : '';
                }

                return $key . '="' . $value . '"';
            })
            ->filter()
            ->implode(' ');

        return '<video' . ($attributes !== '' ? ' ' . $attributes : '') . '>'
            . '<source src="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" type="' . $mimeType . '">'
            . '</video>';
    }

    /**
     * Resolve a provider thumbnail for an external video.
     */
    public function thumbnail(Media|string|null $source): ?string
    {
        if ($source instanceof Media) {
            if (!$source->isVideo() || !$source->isExternalUrl()) {
                return null;
            }

            $storedThumbnail = $source->thumbnailUrl();

            if ($storedThumbnail !== null) {
                return $storedThumbnail;
            }
        }

        $url = $this->embedUrl($source);

        if ($url === null) {
            return null;
        }

        $youtubeThumbnail = $this->youtubeThumbnailUrl($url);

        if ($youtubeThumbnail !== null) {
            return $youtubeThumbnail;
        }

        $thumbnail = Cache::remember(
            'mediaclass:oembed-thumbnail:' . sha1($url),
            now()->addDays(30),
            function () use ($url): string {
                try {
                    return $this->validHttpUrl($this->oEmbedFactory()->get($url)?->thumbnailUrl()) ?? '';
                } catch (Throwable) {
                    return '';
                }
            },
        );

        return $this->validHttpUrl($thumbnail);
    }

    protected function youtubeThumbnailUrl(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $videoId = null;

        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $videoId = explode('/', $path)[0] ?? null;
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            $videoId = $query['v'] ?? null;

            if (!is_string($videoId) && preg_match('#^(?:embed|shorts|live)/([^/]+)#', $path, $matches)) {
                $videoId = $matches[1];
            }
        }

        if (!is_string($videoId) || preg_match('/^[A-Za-z0-9_-]+$/', $videoId) !== 1) {
            return null;
        }

        return 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg';
    }

    protected function embedUrl(Media|string|null $source): ?string
    {
        if ($source instanceof Media) {
            if (!$source->isVideo() || !$source->isExternalUrl()) {
                return null;
            }

            $source = $source->externalUrl();
        }

        if (!is_string($source)) {
            return null;
        }

        $url = trim($source);
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (
            filter_var($url, FILTER_VALIDATE_URL) === false
            || !is_string($scheme)
            || !in_array(strtolower($scheme), ['http', 'https'], true)
        ) {
            return null;
        }

        return $url;
    }

    protected function validHttpUrl(mixed $url): ?string
    {
        if (!is_string($url)) {
            return null;
        }

        $url = trim($url);
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (
            filter_var($url, FILTER_VALIDATE_URL) === false
            || !is_string($scheme)
            || !in_array(strtolower($scheme), ['http', 'https'], true)
        ) {
            return null;
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, bool|float|int|string>
     */
    protected function sanitizeEmbedOptions(array $options): array
    {
        $allowed = [
            'allow',
            'allowfullscreen',
            'autoplay',
            'class',
            'controls',
            'height',
            'loading',
            'referrerpolicy',
            'title',
            'width',
        ];

        return collect($options)
            ->only($allowed)
            ->filter(fn (mixed $value): bool => is_bool($value) || is_float($value) || is_int($value) || is_string($value))
            ->map(fn (mixed $value): mixed => is_string($value)
                ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                : $value)
            ->all();
    }

    protected function oEmbedFactory(): Factory
    {
        return $this->oEmbedFactory ??= new Factory;
    }

    /**
     * Get a single URL for a ghost model's media
     * Automatically returns cropped version if available, otherwise returns requested size
     *
     * @param  string  $modelClass  The fully qualified class name of the model
     * @param  string|null  $group  Optional group filter
     * @param  string  $size  The size to retrieve (sm, md, lg, xl) - ignored if cropped exists
     * @param  string|null  $default  Default URL if no media found (null returns empty string)
     * @return string The URL (cropped if available, otherwise requested size)
     */
    public static function ghostUrl(string $modelClass, ?string $group = null, string $size = 'sm', ?string $default = null): string
    {
        // Handle morph map
        $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
        $morphType = array_search($modelClass, $morphMap) ?: $modelClass;

        // Build query
        $query = Media::where('model_type', $morphType)
            ->whereNull('model_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->orderBy('id');

        if ($group !== null) {
            $query->where('group', $group);
        }

        // Get first media
        $media = $query->first();

        if (!$media) {
            return $default ?: '';
        }

        // Create ghost model instance and inject it
        if (class_exists($modelClass)) {
            $ghostModel = new $modelClass;
            $media->setRelation('model', $ghostModel);
        }

        // Check if cropped version exists first
        if ($group && $media->isCroppedForKey($group)) {
            // The group name is used as the crop key for ghost models
            return $media->getCroppedUrl($group) ?: ($default ?: '');
        }

        // Check for any other cropped version
        if ($media->isCropped()) {
            return $media->url('cropped');
        }

        // Otherwise return the requested size
        $url = $media->url($size);

        // If no URL found, try to construct it manually for files without extensions
        if (!$url && $media->sizeable()) {
            $folder = Path::mediaFolderForMedia($media);
            $sizes = Config::getSizes();

            // Try to find a file that matches our pattern
            if (isset($sizes[$size])) {
                $width = $sizes[$size]['width'];
                $filename = $width . '_' . $media->filename;
                $possibleFile = $folder . '/' . $filename;

                // Check if file exists without extension
                if (Config::getDisk()->exists($possibleFile)) {
                    return Config::getDisk()->url($possibleFile);
                }

                // Check with extension
                $possibleFileWithExt = $possibleFile . '.' . $media->extension();
                if (Config::getDisk()->exists($possibleFileWithExt)) {
                    return Config::getDisk()->url($possibleFileWithExt);
                }
            }
        }

        return $url ?: ($default ?: '');
    }
}
