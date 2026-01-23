<?php

namespace MetaFramework\Mediaclass;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use MetaFramework\Mediaclass\Interfaces\MediaclassInterface;
use MetaFramework\Mediaclass\Models\Media;

class Mediaclass
{
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
     * @param MediaclassInterface $object The object implementing MediaclassInterface.
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
     * @param string $group The group name for filtering.
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
                $item->model = $this->object;
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
     * @param Media $instance The Media instance to parse.
     * @return static The current instance for method chaining.
     */
    protected function parseMedia(Media $instance): static
    {
        // Only set model if we have an object and the media doesn't already have a model relation
        if (isset($this->object) && !$instance->relationLoaded('model')) {
            $instance->model = $this->object;
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
     * @param MediaclassInterface|null $object The model to get media for.
     * @param string|null $group Optional group filter.
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
     * @param string $identifier The subgroup identifier.
     * @return EloquentCollection Filtered collection.
     */
    public function forSubGroup(string $identifier): EloquentCollection
    {
        $collection = $this->get();
        return $collection->filter(fn($item) => $item->subgroup == $identifier);
    }

    /**
     * Gets parsed media for a specific subgroup.
     *
     * @param string $identifier The subgroup identifier.
     * @return Collection Collection of Parser instances.
     */
    public function parsedForSubGroup(string $identifier): Collection
    {
        return $this->forSubGroup($identifier)->map(fn($item) => new Parser($item));
    }

    /**
     * Filters the media collection by group.
     *
     * @param string $identifier The group identifier.
     * @return EloquentCollection Filtered collection.
     */
    public function forGroup(string $identifier): EloquentCollection
    {
        $collection = $this->get();
        return $collection->filter(fn($item) => $item->group == $identifier);
    }

    /**
     * Gets parsed media for a specific group.
     *
     * @param string $identifier The group identifier.
     * @return Collection Collection of Parser instances.
     */
    public function parsedForGroup(string $identifier): Collection
    {
        return $this->forGroup($identifier)->map(fn($item) => new Parser($item));
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
     * @param callable $callback The callback to apply to each item.
     * @return array The mapped results.
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->media);
    }

    /**
     * Filters the parsed media items.
     *
     * @param callable $callback The filter callback.
     * @return array The filtered items.
     */
    public function filter(callable $callback): array
    {
        return array_filter($this->media, $callback);
    }

    /**
     * Get a single URL for a ghost model's media
     * Automatically returns cropped version if available, otherwise returns requested size
     *
     * @param string $modelClass The fully qualified class name of the model
     * @param string|null $group Optional group filter
     * @param string $size The size to retrieve (sm, md, lg, xl) - ignored if cropped exists
     * @param string|null $default Default URL if no media found (null returns empty string)
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
            ->orderBy('position')
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
            $ghostModel = new $modelClass();
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