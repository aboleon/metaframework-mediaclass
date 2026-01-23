<?php

namespace MetaFramework\Mediaclass\Components;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use MetaFramework\Mediaclass\MediaBuilder;
use MetaFramework\Mediaclass\Models\Media as MediaModel;
use MetaFramework\Mediaclass\Parser;

/**
 * Simplified media display component.
 *
 * @example
 * // Basic usage
 * <x-mfw-media :src="$post->img('cover')" />
 *
 * // With attributes
 * <x-mfw-media :src="$post->img('cover')" class="rounded" size="lg" lazy />
 *
 * // From model with group
 * <x-mfw-media :model="$post" group="cover" />
 *
 * // As URL only
 * <x-mfw-media :src="$post->img('cover')" type="url" />
 *
 * // As picture element
 * <x-mfw-media :src="$post->img('cover')" type="picture" />
 */
class Media extends Component
{
    public MediaBuilder $builder;
    public string $type;

    public function __construct(
        public MediaBuilder|MediaModel|Parser|null $src = null,
        public ?object $model = null,
        public ?string $group = null,
        public ?string $subgroup = null,
        public string $size = 'sm',
        public ?string $class = null,
        public ?string $alt = null,
        public ?string $id = null,
        string $type = 'img',
        public bool $lazy = false,
        public bool $eager = false,
        public ?string $default = null,
        public bool $noDefault = false,
        public ?string $crop = null,
        public ?array $breakpoints = null,
        public array $data = [],
    ) {
        $this->type = $type;
        $this->builder = $this->resolveBuilder();
        $this->applyAttributes();
    }

    protected function resolveBuilder(): MediaBuilder
    {
        // If src is already a MediaBuilder, use it
        if ($this->src instanceof MediaBuilder) {
            return $this->src;
        }

        // If src is a Media model, wrap it
        if ($this->src instanceof MediaModel) {
            return new MediaBuilder($this->src);
        }

        // If src is a Parser, extract media and wrap
        if ($this->src instanceof Parser) {
            return new MediaBuilder($this->src->getMedia());
        }

        // If model + group provided, fetch from model
        if ($this->model && $this->group && method_exists($this->model, 'img')) {
            return $this->model->img($this->group, $this->subgroup);
        }

        // Return empty builder
        return new MediaBuilder(null);
    }

    protected function applyAttributes(): void
    {
        // Apply size
        $this->builder->size($this->size);

        // Apply class
        if ($this->class) {
            $this->builder->class($this->class);
        }

        // Apply alt
        if ($this->alt) {
            $this->builder->alt($this->alt);
        }

        // Apply id
        if ($this->id) {
            $this->builder->id($this->id);
        }

        // Apply loading
        if ($this->lazy) {
            $this->builder->lazy();
        } elseif ($this->eager) {
            $this->builder->eager();
        }

        // Apply default
        if ($this->noDefault) {
            $this->builder->noDefault();
        } elseif ($this->default) {
            $this->builder->default($this->default);
        }

        // Apply crop
        if ($this->crop) {
            $this->builder->crop($this->crop);
        }

        // Apply data attributes
        foreach ($this->data as $key => $value) {
            $this->builder->data($key, $value);
        }
    }

    public function render(): Renderable|HtmlString|string
    {
        return match ($this->type) {
            'url' => $this->builder->url(),
            'picture' => $this->builder->picture($this->breakpoints),
            'background' => $this->builder->background(),
            default => $this->builder->img(),
        };
    }
}
