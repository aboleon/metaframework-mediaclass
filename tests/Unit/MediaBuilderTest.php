<?php

namespace MetaFramework\Mediaclass\Tests\Unit;

use Illuminate\Support\HtmlString;
use MetaFramework\Mediaclass\MediaBuilder;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Tests\Fixtures\Post;
use MetaFramework\Mediaclass\Tests\TestCase;

class MediaBuilderTest extends TestCase
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

    public function test_can_create_builder_with_media(): void
    {
        $builder = new MediaBuilder($this->media);

        $this->assertTrue($builder->exists());
        $this->assertSame($this->media, $builder->getMedia());
    }

    public function test_can_create_builder_without_media(): void
    {
        $builder = new MediaBuilder(null);

        $this->assertFalse($builder->exists());
        $this->assertNull($builder->getMedia());
    }

    public function test_can_set_size(): void
    {
        $builder = new MediaBuilder($this->media);

        $result = $builder->size('lg');

        $this->assertSame($builder, $result);
    }

    public function test_size_shorthand_methods(): void
    {
        $builder = new MediaBuilder($this->media);

        $this->assertSame($builder, $builder->sm());
        $this->assertSame($builder, $builder->md());
        $this->assertSame($builder, $builder->lg());
        $this->assertSame($builder, $builder->xl());
    }

    public function test_can_set_class(): void
    {
        $builder = new MediaBuilder($this->media);

        $result = $builder->class('rounded shadow');

        $this->assertSame($builder, $result);
    }

    public function test_can_add_class(): void
    {
        $builder = new MediaBuilder($this->media);

        $builder->class('rounded')->addClass('shadow');

        $img = (string) $builder->img();

        $this->assertStringContainsString('class="rounded shadow"', $img);
    }

    public function test_can_set_alt(): void
    {
        $builder = new MediaBuilder($this->media);

        $builder->alt('Test image');

        $img = (string) $builder->img();

        $this->assertStringContainsString('alt="Test image"', $img);
    }

    public function test_can_set_id(): void
    {
        $builder = new MediaBuilder($this->media);

        $builder->id('hero-image');

        $img = (string) $builder->img();

        $this->assertStringContainsString('id="hero-image"', $img);
    }

    public function test_can_set_lazy_loading(): void
    {
        $builder = new MediaBuilder($this->media);

        $builder->lazy();

        $img = (string) $builder->img();

        $this->assertStringContainsString('loading="lazy"', $img);
    }

    public function test_can_set_eager_loading(): void
    {
        $builder = new MediaBuilder($this->media);

        $builder->eager();

        $img = (string) $builder->img();

        $this->assertStringContainsString('loading="eager"', $img);
    }

    public function test_can_set_width_and_height(): void
    {
        $builder = new MediaBuilder($this->media);

        $builder->width(800)->height(600);

        $img = (string) $builder->img();

        $this->assertStringContainsString('width="800"', $img);
        $this->assertStringContainsString('height="600"', $img);
    }

    public function test_can_set_custom_attribute(): void
    {
        $builder = new MediaBuilder($this->media);

        $builder->attr('data-gallery', 'main');

        $img = (string) $builder->img();

        $this->assertStringContainsString('data-gallery="main"', $img);
    }

    public function test_can_set_multiple_attributes(): void
    {
        $builder = new MediaBuilder($this->media);

        $builder->attrs(['class' => 'rounded', 'id' => 'test']);

        $img = (string) $builder->img();

        $this->assertStringContainsString('class="rounded"', $img);
        $this->assertStringContainsString('id="test"', $img);
    }

    public function test_can_set_data_attribute(): void
    {
        $builder = new MediaBuilder($this->media);

        $builder->data('id', '123');

        $img = (string) $builder->img();

        $this->assertStringContainsString('data-id="123"', $img);
    }

    public function test_can_set_default_url(): void
    {
        $builder = new MediaBuilder(null);

        $builder->default('/img/fallback.png');

        $this->assertEquals('/img/fallback.png', $builder->url());
    }

    public function test_can_disable_default(): void
    {
        $builder = new MediaBuilder(null);

        $builder->noDefault();

        $this->assertEquals('', $builder->url());
    }

    public function test_img_returns_html_string(): void
    {
        $builder = new MediaBuilder($this->media);

        $result = $builder->img();

        $this->assertInstanceOf(HtmlString::class, $result);
    }

    public function test_img_contains_src_attribute(): void
    {
        $builder = new MediaBuilder($this->media);

        $img = (string) $builder->img();

        $this->assertStringContainsString('<img', $img);
        $this->assertStringContainsString('src="', $img);
    }

    public function test_img_returns_empty_when_no_media_and_no_default(): void
    {
        $builder = new MediaBuilder(null);
        $builder->noDefault();

        $img = (string) $builder->img();

        $this->assertEquals('', $img);
    }

    public function test_picture_returns_html_string(): void
    {
        $builder = new MediaBuilder($this->media);

        $result = $builder->picture();

        $this->assertInstanceOf(HtmlString::class, $result);
        $this->assertStringContainsString('<picture>', (string) $result);
        $this->assertStringContainsString('</picture>', (string) $result);
    }

    public function test_background_returns_css_style(): void
    {
        $builder = new MediaBuilder($this->media);

        $result = $builder->background();

        $this->assertStringContainsString('background-image: url(', $result);
    }

    public function test_background_returns_empty_when_no_media(): void
    {
        $builder = new MediaBuilder(null);
        $builder->noDefault();

        $result = $builder->background();

        $this->assertEquals('', $result);
    }

    public function test_to_string_returns_url(): void
    {
        $builder = new MediaBuilder($this->media);

        $result = (string) $builder;

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function test_fluent_chaining(): void
    {
        $builder = new MediaBuilder($this->media);

        $result = $builder
            ->size('lg')
            ->class('rounded')
            ->alt('Test')
            ->lazy()
            ->id('hero');

        $this->assertSame($builder, $result);
    }

    public function test_img_with_size_parameter(): void
    {
        $builder = new MediaBuilder($this->media);

        $img = (string) $builder->img('lg');

        $this->assertStringContainsString('<img', $img);
    }

    public function test_img_with_attributes_parameter(): void
    {
        $builder = new MediaBuilder($this->media);

        $img = (string) $builder->img(null, ['class' => 'custom-class']);

        $this->assertStringContainsString('class="custom-class"', $img);
    }

    public function test_urls_returns_array(): void
    {
        $builder = new MediaBuilder($this->media);

        $urls = $builder->urls();

        $this->assertIsArray($urls);
    }

    public function test_sizes_returns_array(): void
    {
        $builder = new MediaBuilder($this->media);

        $sizes = $builder->sizes();

        $this->assertIsArray($sizes);
    }

    public function test_escapes_html_in_attributes(): void
    {
        $builder = new MediaBuilder($this->media);

        $builder->alt('Test "quoted" <script>');

        $img = (string) $builder->img();

        $this->assertStringContainsString('alt="Test &quot;quoted&quot; &lt;script&gt;"', $img);
    }
}
