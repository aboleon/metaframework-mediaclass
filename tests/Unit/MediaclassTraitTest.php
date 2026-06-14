<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Unit;

use Illuminate\Support\Collection;
use MetaFramework\Mediaclass\MediaBuilder;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Tests\Fixtures\Post;
use MetaFramework\Mediaclass\Tests\TestCase;

class MediaclassTraitTest extends TestCase
{
    protected Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->post = Post::create(['title' => 'Test Post']);
    }

    public function test_img_returns_media_builder(): void
    {
        $result = $this->post->img('cover');

        $this->assertInstanceOf(MediaBuilder::class, $result);
    }

    public function test_img_returns_builder_even_without_media(): void
    {
        $result = $this->post->img('nonexistent');

        $this->assertInstanceOf(MediaBuilder::class, $result);
        $this->assertFalse($result->exists());
    }

    public function test_img_returns_builder_with_media_when_exists(): void
    {
        Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'test.jpg',
            'filename' => 'abc123',
            'position' => 'left',
        ]);

        $result = $this->post->img('cover');

        $this->assertTrue($result->exists());
        $this->assertNotNull($result->getMedia());
    }

    public function test_img_filters_by_group(): void
    {
        Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'cover.jpg',
            'filename' => 'cover123',
            'position' => 'left',
        ]);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'gallery',
            'mime' => 'image/jpeg',
            'original_filename' => 'gallery.jpg',
            'filename' => 'gallery123',
            'position' => 'left',
        ]);

        $cover = $this->post->img('cover');
        $gallery = $this->post->img('gallery');

        $this->assertEquals('cover123', $cover->getMedia()->filename);
        $this->assertEquals('gallery123', $gallery->getMedia()->filename);
    }

    public function test_img_filters_by_subgroup(): void
    {
        Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'gallery',
            'subgroup' => 'front',
            'mime' => 'image/jpeg',
            'original_filename' => 'front.jpg',
            'filename' => 'front123',
            'position' => 'left',
        ]);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'gallery',
            'subgroup' => 'back',
            'mime' => 'image/jpeg',
            'original_filename' => 'back.jpg',
            'filename' => 'back123',
            'position' => 'left',
        ]);

        $front = $this->post->img('gallery', 'front');
        $back = $this->post->img('gallery', 'back');

        $this->assertEquals('front123', $front->getMedia()->filename);
        $this->assertEquals('back123', $back->getMedia()->filename);
    }

    public function test_img_returns_first_by_sort_order_independently_of_flow_position(): void
    {
        Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'gallery',
            'mime' => 'image/jpeg',
            'original_filename' => 'second.jpg',
            'filename' => 'second',
            'position' => 'left',
            'sort_order' => 2,
        ]);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'gallery',
            'mime' => 'image/jpeg',
            'original_filename' => 'first.jpg',
            'filename' => 'first',
            'position' => 'right',
            'sort_order' => 1,
        ]);

        $result = $this->post->img('gallery');

        $this->assertSame('first', $result->getMedia()?->filename);
    }

    public function test_imgs_returns_media_in_sort_order(): void
    {
        foreach ([
            ['filename' => 'third', 'sort_order' => 3],
            ['filename' => 'first', 'sort_order' => 1],
            ['filename' => 'second', 'sort_order' => 2],
        ] as $media) {
            Media::create([
                'model_type' => Post::class,
                'model_id' => $this->post->id,
                'group' => 'gallery',
                'mime' => 'image/jpeg',
                'original_filename' => $media['filename'] . '.jpg',
                'filename' => $media['filename'],
                'position' => 'left',
                'sort_order' => $media['sort_order'],
            ]);
        }

        $filenames = $this->post->imgs('gallery')
            ->map(fn (MediaBuilder $builder): ?string => $builder->getMedia()?->filename)
            ->all();

        $this->assertSame(['first', 'second', 'third'], $filenames);
    }

    public function test_imgs_returns_collection(): void
    {
        $result = $this->post->imgs('gallery');

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_imgs_returns_empty_collection_when_no_media(): void
    {
        $result = $this->post->imgs('gallery');

        $this->assertTrue($result->isEmpty());
    }

    public function test_imgs_returns_collection_of_media_builders(): void
    {
        Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'gallery',
            'mime' => 'image/jpeg',
            'original_filename' => 'one.jpg',
            'filename' => 'one',
            'position' => 'left',
        ]);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'gallery',
            'mime' => 'image/jpeg',
            'original_filename' => 'two.jpg',
            'filename' => 'two',
            'position' => 'left',
        ]);

        $result = $this->post->imgs('gallery');

        $this->assertCount(2, $result);
        $this->assertInstanceOf(MediaBuilder::class, $result->first());
        $this->assertInstanceOf(MediaBuilder::class, $result->last());
    }

    public function test_imgs_filters_by_group(): void
    {
        Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'gallery',
            'mime' => 'image/jpeg',
            'original_filename' => 'gallery.jpg',
            'filename' => 'gallery',
            'position' => 'left',
        ]);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'cover.jpg',
            'filename' => 'cover',
            'position' => 'left',
        ]);

        $gallery = $this->post->imgs('gallery');
        $cover = $this->post->imgs('cover');

        $this->assertCount(1, $gallery);
        $this->assertCount(1, $cover);
    }

    public function test_imgs_filters_by_subgroup(): void
    {
        Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'gallery',
            'subgroup' => 'featured',
            'mime' => 'image/jpeg',
            'original_filename' => 'featured.jpg',
            'filename' => 'featured',
            'position' => 'left',
        ]);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'gallery',
            'subgroup' => 'regular',
            'mime' => 'image/jpeg',
            'original_filename' => 'regular.jpg',
            'filename' => 'regular',
            'position' => 'left',
        ]);

        $featured = $this->post->imgs('gallery', 'featured');
        $regular = $this->post->imgs('gallery', 'regular');

        $this->assertCount(1, $featured);
        $this->assertCount(1, $regular);
    }

    public function test_imgs_can_map_to_urls(): void
    {
        Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'gallery',
            'mime' => 'image/jpeg',
            'original_filename' => 'one.jpg',
            'filename' => 'one',
            'position' => 'left',
        ]);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'gallery',
            'mime' => 'image/jpeg',
            'original_filename' => 'two.jpg',
            'filename' => 'two',
            'position' => 'left',
        ]);

        $urls = $this->post->imgs('gallery')->map(fn ($img) => $img->url());

        $this->assertCount(2, $urls);
        $this->assertIsString($urls->first());
    }

    public function test_fluent_api_from_model(): void
    {
        Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'test.jpg',
            'filename' => 'test123',
            'position' => 'left',
        ]);

        $url = $this->post->img('cover')->url();
        $img = (string) $this->post->img('cover')->class('rounded')->lazy()->img();

        $this->assertIsString($url);
        $this->assertStringContainsString('<img', $img);
        $this->assertStringContainsString('class="rounded"', $img);
        $this->assertStringContainsString('loading="lazy"', $img);
    }
}
