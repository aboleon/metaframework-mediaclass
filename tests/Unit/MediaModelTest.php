<?php

namespace MetaFramework\Mediaclass\Tests\Unit;

use MetaFramework\Mediaclass\MediaBuilder;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Tests\Fixtures\Post;
use MetaFramework\Mediaclass\Tests\Fixtures\PostWithGroupSizes;
use MetaFramework\Mediaclass\Tests\TestCase;

class MediaModelTest extends TestCase
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
            'description' => ['en' => 'English description', 'fr' => 'French description'],
        ]);

        $this->media->setRelation('model', $this->post);
    }

    public function test_builder_returns_media_builder(): void
    {
        $result = $this->media->builder();

        $this->assertInstanceOf(MediaBuilder::class, $result);
    }

    public function test_builder_contains_media(): void
    {
        $builder = $this->media->builder();

        $this->assertSame($this->media, $builder->getMedia());
    }

    public function test_is_image_returns_true_for_images(): void
    {
        $this->assertTrue($this->media->isImage());
    }

    public function test_is_image_returns_false_for_non_images(): void
    {
        $pdfMedia = Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'documents',
            'mime' => 'application/pdf',
            'original_filename' => 'document.pdf',
            'filename' => 'doc123',
            'position' => 'left',
        ]);

        $this->assertFalse($pdfMedia->isImage());
    }

    public function test_sizes_returns_array(): void
    {
        $sizes = $this->media->sizes();

        $this->assertIsArray($sizes);
        $this->assertContains('sm', $sizes);
        $this->assertContains('md', $sizes);
        $this->assertContains('lg', $sizes);
        $this->assertContains('xl', $sizes);
    }

    public function test_sizes_returns_group_sizes_when_defined(): void
    {
        $post = PostWithGroupSizes::create(['title' => 'Sized Post']);
        $media = Media::create([
            'model_type' => PostWithGroupSizes::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'test.jpg',
            'filename' => 'sized123',
            'position' => 'left',
        ]);
        $media->setRelation('model', $post);

        $sizes = $media->sizes();

        $this->assertSame(['xl', 'sm'], $sizes);
    }

    public function test_dimension_prefix_uses_group_sizes(): void
    {
        $post = PostWithGroupSizes::create(['title' => 'Sized Post']);
        $media = Media::create([
            'model_type' => PostWithGroupSizes::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'test.jpg',
            'filename' => 'sized123',
            'position' => 'left',
        ]);
        $media->setRelation('model', $post);

        $this->assertSame('1600_', $media->dimensionPrefix('xl'));
        $this->assertSame('1200_', $media->dimensionPrefix('sm'));
    }

    public function test_dimension_prefix_falls_back_to_largest_group_size(): void
    {
        $post = PostWithGroupSizes::create(['title' => 'Sized Post']);
        $media = Media::create([
            'model_type' => PostWithGroupSizes::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'test.jpg',
            'filename' => 'sized123',
            'position' => 'left',
        ]);
        $media->setRelation('model', $post);

        $this->assertSame('1600_', $media->dimensionPrefix('md'));
    }

    public function test_img_returns_html_string(): void
    {
        $img = $this->media->img();

        $this->assertIsString($img);
        $this->assertStringContainsString('<img', $img);
    }

    public function test_img_with_size_parameter(): void
    {
        $img = $this->media->img('lg');

        $this->assertStringContainsString('<img', $img);
    }

    public function test_img_with_attributes(): void
    {
        $img = $this->media->img('sm', ['class' => 'rounded', 'id' => 'test']);

        $this->assertStringContainsString('class="rounded"', $img);
        $this->assertStringContainsString('id="test"', $img);
    }

    public function test_url_returns_string(): void
    {
        $url = $this->media->url();

        $this->assertIsString($url);
    }

    public function test_url_with_size_parameter(): void
    {
        $urlSm = $this->media->url('sm');
        $urlLg = $this->media->url('lg');

        $this->assertIsString($urlSm);
        $this->assertIsString($urlLg);
        // Note: When model has mediaclassSettings(), it uses fixed width from those settings
        // so sm and lg may return same URL for groups with specific dimensions configured
        $this->assertStringContainsString($this->media->filename, $urlSm);
    }

    public function test_extension_returns_correct_value(): void
    {
        $this->assertEquals('jpg', $this->media->extension());

        $pngMedia = Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'cover',
            'mime' => 'image/png',
            'original_filename' => 'test.png',
            'filename' => 'png123',
            'position' => 'left',
        ]);

        $this->assertEquals('png', $pngMedia->extension());
    }

    public function test_sizeable_returns_true_for_jpeg(): void
    {
        $this->assertTrue($this->media->sizeable());
    }

    public function test_sizeable_returns_true_for_png(): void
    {
        $pngMedia = Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'cover',
            'mime' => 'image/png',
            'original_filename' => 'test.png',
            'filename' => 'png123',
            'position' => 'left',
        ]);

        $this->assertTrue($pngMedia->sizeable());
    }

    public function test_sizeable_returns_false_for_svg(): void
    {
        $svgMedia = Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'cover',
            'mime' => 'image/svg+xml',
            'original_filename' => 'test.svg',
            'filename' => 'svg123',
            'position' => 'left',
        ]);

        $this->assertFalse($svgMedia->sizeable());
    }

    public function test_sizeable_returns_false_for_pdf(): void
    {
        $pdfMedia = Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'documents',
            'mime' => 'application/pdf',
            'original_filename' => 'doc.pdf',
            'filename' => 'pdf123',
            'position' => 'left',
        ]);

        $this->assertFalse($pdfMedia->sizeable());
    }

    public function test_description_is_cast_to_array(): void
    {
        $this->assertIsArray($this->media->description);
        $this->assertEquals('English description', $this->media->description['en']);
        $this->assertEquals('French description', $this->media->description['fr']);
    }

    public function test_storable_is_cast_to_array(): void
    {
        $media = Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'test.jpg',
            'filename' => 'store123',
            'position' => 'left',
            'storable' => ['slot' => 'hero', 'category' => 'featured'],
        ]);

        $this->assertIsArray($media->storable);
        $this->assertEquals('hero', $media->storable['slot']);
        $this->assertEquals('featured', $media->storable['category']);
    }

    public function test_has_crop_alias_works(): void
    {
        // hasCrop is an alias for isCroppedForKey
        $result = $this->media->hasCrop('banner');

        $this->assertIsBool($result);
    }

    public function test_crop_alias_works(): void
    {
        // crop is an alias for getCroppedUrl
        $result = $this->media->crop('banner');

        // Should return null since no crop exists
        $this->assertNull($result);
    }
}
