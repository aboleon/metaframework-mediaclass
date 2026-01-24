<?php

namespace MetaFramework\Mediaclass\Tests\Feature;

use MetaFramework\Mediaclass\Components\Media as MediaComponent;
use MetaFramework\Mediaclass\MediaBuilder;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Parser;
use MetaFramework\Mediaclass\Tests\Fixtures\Post;
use MetaFramework\Mediaclass\Tests\TestCase;

class MediaComponentTest extends TestCase
{
    protected Post $post;
    protected Media $media;

    protected function setUp(): void
    {
        parent::setUp();

        $this->post = Post::create(['title' => 'Test Post']);

        $this->media = Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'test.jpg',
            'filename' => 'abc123',
            'position' => 'left',
        ]);

        $this->media->setRelation('model', $this->post);
    }

    public function test_component_accepts_media_builder(): void
    {
        $builder = new MediaBuilder($this->media);

        $component = new MediaComponent(src: $builder);

        $this->assertInstanceOf(MediaBuilder::class, $component->builder);
    }

    public function test_component_accepts_media_model(): void
    {
        $component = new MediaComponent(src: $this->media);

        $this->assertInstanceOf(MediaBuilder::class, $component->builder);
        $this->assertTrue($component->builder->exists());
    }

    public function test_component_accepts_parser(): void
    {
        $parser = new Parser($this->media);

        $component = new MediaComponent(src: $parser);

        $this->assertInstanceOf(MediaBuilder::class, $component->builder);
    }

    public function test_component_accepts_model_with_group(): void
    {
        $component = new MediaComponent(
            model: $this->post,
            group: 'cover'
        );

        $this->assertInstanceOf(MediaBuilder::class, $component->builder);
        $this->assertTrue($component->builder->exists());
    }

    public function test_component_applies_size(): void
    {
        $builder = new MediaBuilder($this->media);

        $component = new MediaComponent(src: $builder, size: 'lg');

        $this->assertEquals('lg', $component->size);
    }

    public function test_component_applies_class(): void
    {
        $builder = new MediaBuilder($this->media);

        $component = new MediaComponent(src: $builder, class: 'rounded shadow');

        $rendered = (string) $component->render();

        $this->assertStringContainsString('class="rounded shadow"', $rendered);
    }

    public function test_component_applies_alt(): void
    {
        $builder = new MediaBuilder($this->media);

        $component = new MediaComponent(src: $builder, alt: 'Test image');

        $rendered = (string) $component->render();

        $this->assertStringContainsString('alt="Test image"', $rendered);
    }

    public function test_component_applies_id(): void
    {
        $builder = new MediaBuilder($this->media);

        $component = new MediaComponent(src: $builder, id: 'hero-image');

        $rendered = (string) $component->render();

        $this->assertStringContainsString('id="hero-image"', $rendered);
    }

    public function test_component_applies_lazy_loading(): void
    {
        $builder = new MediaBuilder($this->media);

        $component = new MediaComponent(src: $builder, lazy: true);

        $rendered = (string) $component->render();

        $this->assertStringContainsString('loading="lazy"', $rendered);
    }

    public function test_component_applies_eager_loading(): void
    {
        $builder = new MediaBuilder($this->media);

        $component = new MediaComponent(src: $builder, eager: true);

        $rendered = (string) $component->render();

        $this->assertStringContainsString('loading="eager"', $rendered);
    }

    public function test_component_renders_img_by_default(): void
    {
        $builder = new MediaBuilder($this->media);

        $component = new MediaComponent(src: $builder);

        $rendered = (string) $component->render();

        $this->assertStringContainsString('<img', $rendered);
    }

    public function test_component_renders_url_when_type_is_url(): void
    {
        $builder = new MediaBuilder($this->media);

        $component = new MediaComponent(src: $builder, type: 'url');

        $rendered = (string) $component->render();

        $this->assertStringNotContainsString('<img', $rendered);
        $this->assertIsString($rendered);
    }

    public function test_component_renders_picture_when_type_is_picture(): void
    {
        $builder = new MediaBuilder($this->media);

        $component = new MediaComponent(src: $builder, type: 'picture');

        $rendered = (string) $component->render();

        $this->assertStringContainsString('<picture>', $rendered);
    }

    public function test_component_renders_background_when_type_is_background(): void
    {
        $builder = new MediaBuilder($this->media);

        $component = new MediaComponent(src: $builder, type: 'background');

        $rendered = (string) $component->render();

        $this->assertStringContainsString('background-image:', $rendered);
    }

    public function test_component_applies_data_attributes(): void
    {
        $builder = new MediaBuilder($this->media);

        $component = new MediaComponent(
            src: $builder,
            data: ['gallery' => 'main', 'index' => '0']
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('data-gallery="main"', $rendered);
        $this->assertStringContainsString('data-index="0"', $rendered);
    }

    public function test_component_applies_custom_default(): void
    {
        $component = new MediaComponent(
            src: null,
            default: '/img/fallback.png'
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('/img/fallback.png', $rendered);
    }

    public function test_component_respects_no_default(): void
    {
        $component = new MediaComponent(
            src: null,
            noDefault: true
        );

        $rendered = (string) $component->render();

        $this->assertEquals('', $rendered);
    }

    public function test_component_applies_crop(): void
    {
        $builder = new MediaBuilder($this->media);

        $component = new MediaComponent(
            src: $builder,
            crop: 'banner'
        );

        // Crop should be set on builder
        $this->assertInstanceOf(MediaBuilder::class, $component->builder);
    }

    public function test_component_handles_null_src_gracefully(): void
    {
        $component = new MediaComponent(src: null, noDefault: true);

        $this->assertFalse($component->builder->exists());
    }
}
