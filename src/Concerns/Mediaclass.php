<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use MetaFramework\Accessors\Locale;
use MetaFramework\Mediaclass\MediaBuilder;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Support\Config;
use MetaFramework\Mediaclass\Support\Path;
use MetaFramework\Support\Traits\Responses;
use ReflectionClass;
use Throwable;

trait Mediaclass
{
    use Responses;

    public ?object $instance = null;

    /**
     * Get the media relationship.
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'model');
    }

    /**
     * Get a fluent MediaBuilder for a single media in a group.
     *
     * @example
     * // Get URL
     * $post->img('cover')->url();
     * $post->img('cover')->url('lg');
     *
     * // Get img tag
     * $post->img('cover')->class('rounded')->lazy()->img();
     *
     * // Get cropped version
     * $post->img('cover')->crop('banner')->url();
     */
    public function img(string $group, ?string $subgroup = null): MediaBuilder
    {
        $media = $this->mediaForMediaclassGroup($group, $subgroup)->first();

        $builder = new MediaBuilder($media);

        // Inject model into media for path resolution
        if ($media) {
            $media->setRelation('model', $this);
        }

        return $builder;
    }

    /**
     * Get MediaBuilder instances for all media in a group.
     *
     * @example
     * // Loop through gallery
     * foreach ($post->imgs('gallery') as $img) {
     *     echo $img->url();
     * }
     *
     * // Get all URLs
     * $urls = $post->imgs('gallery')->map(fn($img) => $img->url());
     */
    public function imgs(string $group, ?string $subgroup = null): Collection
    {
        return $this->mediaForMediaclassGroup($group, $subgroup)->map(function (Media $media) {
            $media->setRelation('model', $this);

            return new MediaBuilder($media);
        });
    }

    private function mediaForMediaclassGroup(string $group, ?string $subgroup = null): Collection
    {
        if (method_exists($this, 'relationLoaded') && $this->relationLoaded('media')) {
            return $this->loadedMediaForMediaclassGroup($group, $subgroup);
        }

        $query = $this->media()->where('group', $group);

        if ($subgroup !== null) {
            $query->where('subgroup', $subgroup);
        }

        return $query->orderBy('sort_order')->orderBy('id')->get();
    }

    private function loadedMediaForMediaclassGroup(string $group, ?string $subgroup = null): Collection
    {
        $media = $this->media->where('group', $group);

        if ($subgroup !== null) {
            $media = $media->where('subgroup', $subgroup);
        }

        return $media
            ->sortBy(fn (Media $media): string => sprintf(
                '%010d-%020d',
                (int) $media->sort_order,
                (int) $media->id,
            ))
            ->values();
    }

    public function model(): static
    {
        $this->instance = $this;

        return $this;
    }

    /**
     * Mets à jour les infos relatives aux médias rattachés
     * au Meta model
     */
    public function processMedia(): static
    {
        if (request()->has('mediaclass')) {
            foreach (request('mediaclass') as $key => $value) {
                $media = $this->media()->whereKey($key)->first();

                if (!$media instanceof Media || !is_array($value)) {
                    continue;
                }

                if (array_key_exists('description', $value)) {
                    $media->description = $value['description'];
                }

                if (array_key_exists('position', $value)) {
                    $media->position = $value['position'];
                }

                if ($media->isVideo()) {
                    $storable = (array) ($media->storable ?? []);
                    $storable['embed_width'] = ($value['embed_width_mode'] ?? null) === 'full'
                        ? '100%'
                        : Media::normalizeEmbedWidth($value['embed_width'] ?? $media->embedWidth());
                    $storable['embed_height'] = Media::normalizeEmbedHeight(
                        $value['embed_height'] ?? $media->embedHeight(),
                    );
                    $media->storable = $storable;
                }

                $media->save();
            }
        }

        if (request()->has('mediaclass_bridge') && method_exists($this, 'syncMediaclassBridgeMedia')) {
            $this->syncMediaclassBridgeMedia((array) request('mediaclass_bridge'));
        }

        if (request()->has('mediaclass_temp_id')) {

            $recorded = Media::where('temp', request('mediaclass_temp_id'))->get();

            if ($recorded->isEmpty()) {
                return $this;
            }

            Media::where('temp', request('mediaclass_temp_id'))->update([
                'model_id' => $this->model()->id,
                'temp' => null,
            ]);

            $modelFolder = Path::mediaFolderName($this->model(), true);
            $tempFolder = Path::mediaTempFolderName($this->model());
            $disk = Config::getDisk();

            LazyCollection::make($recorded)->each(function ($row) use ($tempFolder, $modelFolder, $disk) {

                $files = File::glob($disk
                    ->path($tempFolder . DIRECTORY_SEPARATOR . '*' . $row->filename . '*'));

                if ($files) {
                    Path::checkMakeDir($disk->path($modelFolder));
                    foreach ($files as $media) {
                        File::move($media, str_replace($tempFolder, $modelFolder, $media));
                    }
                }

            });

        }

        return $this;
    }

    public function deleteModelMedia(): static
    {

        if ($this->model()->media->isEmpty()) {
            return $this;
        }

        $disk = Config::getDisk();

        try {
            foreach ($this->model()->media as $media) {
                File::delete(File::glob($disk->path($this->accessKey()) . DIRECTORY_SEPARATOR . '*' . $media->filename . '*'));
                $media->delete();
            }
        } catch (Throwable $e) {
            $this->responseException($e);
            report($e);
        }

        return $this;

    }

    /**
     * @throws \ReflectionException
     */
    public function accessKey(): string
    {
        return $this->model()->access_key ?: Str::snake((new ReflectionClass($this->model()))->getShortName());
    }

    public function mediaLocales(): array
    {
        return Locale::projectLocales();
    }

    public function mediaclassSettings(): array
    {
        return [];
    }
}
