<?php

namespace MetaFramework\Mediaclass\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\File;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use MetaFramework\Accessors\Locale;
use MetaFramework\Mediaclass\Config;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Path;
use MetaFramework\Support\Traits\Responses;
use ReflectionClass;
use Throwable;

trait Mediaclass
{
    use Responses;

    public ?object $instance = null;


    /**
     * Retourne tous les médias du model,
     * si elles existent
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'model');
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
