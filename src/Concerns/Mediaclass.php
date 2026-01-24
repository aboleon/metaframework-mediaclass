<?php

namespace MetaFramework\Mediaclass\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use MetaFramework\Accessors\Locale;
use MetaFramework\Mediaclass\Support\Config;
use MetaFramework\Mediaclass\MediaBuilder;
use MetaFramework\Mediaclass\Models\Media;
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
        $query = $this->media()->where('group', $group);

        if ($subgroup !== null) {
            $query->where('subgroup', $subgroup);
        }

        $media = $query->orderBy('position')->first();

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
        $query = $this->media()->where('group', $group);

        if ($subgroup !== null) {
            $query->where('subgroup', $subgroup);
        }

        return $query->orderBy('position')->get()->map(function (Media $media) {
            $media->setRelation('model', $this);
            return new MediaBuilder($media);
        });
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
                Media::where('id', $key)->update([
                    'description' => $value['description'],
                    'position' => $value['position']
                ]);
            }
        }

        if (request()->has('mediaclass_temp_id')) {

            $recorded = Media::where('temp', request('mediaclass_temp_id'))->get();

            if ($recorded->isEmpty()) {
                return $this;
            }

            Media::where('temp', request('mediaclass_temp_id'))->update([
                'model_id' => $this->model()->id,
                'temp' => null
            ]);

            $modelFolder = Path::mediaFolderName($this->model());
            $tempFolder = Path::mediaTempFolderName($this->model());
            $disk = Config::getDisk();

            LazyCollection::make($recorded)->each(function($row) use($tempFolder, $modelFolder, $disk) {

                $files = File::glob($disk
                    ->path($tempFolder . DIRECTORY_SEPARATOR . '*' . $row->filename . '*'));

                if ($files) {
                    Path::checkMakeDir($disk->path($modelFolder));
                    foreach($files as $media) {
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
