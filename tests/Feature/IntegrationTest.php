<?php

namespace MetaFramework\Mediaclass\Tests\Feature;

use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Tests\Fixtures\Post;
use MetaFramework\Mediaclass\Tests\TestCase;

class IntegrationTest extends TestCase
{
    public function test_full_workflow_single_image(): void
    {
        // Create model
        $post = Post::create(['title' => 'My Post']);

        // Create media
        Media::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'cover.jpg',
            'filename' => 'cover123',
            'position' => 'left',
            'description' => ['en' => 'Cover image'],
        ]);

        // Test fluent API
        $builder = $post->img('cover');

        $this->assertTrue($builder->exists());
        $this->assertNotEmpty($builder->url());
        $this->assertStringContainsString('<img', (string) $builder->img());
    }

    public function test_full_workflow_multiple_images(): void
    {
        $post = Post::create(['title' => 'Gallery Post']);

        // Create multiple media
        for ($i = 1; $i <= 3; $i++) {
            Media::create([
                'model_type' => Post::class,
                'model_id' => $post->id,
                'group' => 'gallery',
                'mime' => 'image/jpeg',
                'original_filename' => "image{$i}.jpg",
                'filename' => "img{$i}",
                'position' => 'left',
            ]);
        }

        // Test imgs() method
        $images = $post->imgs('gallery');

        $this->assertCount(3, $images);

        // Test mapping to URLs
        $urls = $images->map(fn($img) => $img->url());
        $this->assertCount(3, $urls);

        // Test each item is usable
        foreach ($images as $img) {
            $this->assertTrue($img->exists());
            $this->assertNotEmpty($img->url());
        }
    }

    public function test_full_workflow_with_subgroups(): void
    {
        $post = Post::create(['title' => 'Subgroup Post']);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'gallery',
            'subgroup' => 'featured',
            'mime' => 'image/jpeg',
            'original_filename' => 'featured.jpg',
            'filename' => 'featured1',
            'position' => 'left',
        ]);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'gallery',
            'subgroup' => 'regular',
            'mime' => 'image/jpeg',
            'original_filename' => 'regular.jpg',
            'filename' => 'regular1',
            'position' => 'left',
        ]);

        // Test filtering by subgroup
        $featured = $post->img('gallery', 'featured');
        $regular = $post->img('gallery', 'regular');

        $this->assertTrue($featured->exists());
        $this->assertTrue($regular->exists());
        $this->assertNotEquals(
            $featured->getMedia()->filename,
            $regular->getMedia()->filename
        );
    }

    public function test_full_workflow_no_media(): void
    {
        $post = Post::create(['title' => 'Empty Post']);

        $builder = $post->img('cover');

        $this->assertFalse($builder->exists());

        // Should return default or empty
        $builder->noDefault();
        $this->assertEquals('', $builder->url());
        $this->assertEquals('', (string) $builder->img());

        // Should return custom default
        $builder->default('/img/placeholder.jpg');
        $this->assertEquals('/img/placeholder.jpg', $builder->url());
    }

    public function test_full_workflow_fluent_chain(): void
    {
        $post = Post::create(['title' => 'Fluent Post']);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'hero.jpg',
            'filename' => 'hero123',
            'position' => 'left',
        ]);

        // Full fluent chain
        $html = (string) $post->img('cover')
            ->size('lg')
            ->class('rounded-lg shadow-md')
            ->alt('Hero image')
            ->id('hero')
            ->lazy()
            ->data('gallery', 'main')
            ->img();

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('class="rounded-lg shadow-md"', $html);
        $this->assertStringContainsString('alt="Hero image"', $html);
        $this->assertStringContainsString('id="hero"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('data-gallery="main"', $html);
    }

    public function test_media_relationship(): void
    {
        $post = Post::create(['title' => 'Relationship Post']);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'test.jpg',
            'filename' => 'test123',
            'position' => 'left',
        ]);

        // Test that the media() relationship still works
        $this->assertCount(1, $post->media);
        $this->assertEquals('cover', $post->media->first()->group);
    }

    public function test_different_groups_same_model(): void
    {
        $post = Post::create(['title' => 'Multi-group Post']);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'cover.jpg',
            'filename' => 'cover1',
            'position' => 'left',
        ]);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'thumbnail',
            'mime' => 'image/jpeg',
            'original_filename' => 'thumb.jpg',
            'filename' => 'thumb1',
            'position' => 'left',
        ]);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'gallery',
            'mime' => 'image/jpeg',
            'original_filename' => 'gallery.jpg',
            'filename' => 'gallery1',
            'position' => 'left',
        ]);

        $cover = $post->img('cover');
        $thumb = $post->img('thumbnail');
        $gallery = $post->img('gallery');

        $this->assertTrue($cover->exists());
        $this->assertTrue($thumb->exists());
        $this->assertTrue($gallery->exists());

        $this->assertEquals('cover1', $cover->getMedia()->filename);
        $this->assertEquals('thumb1', $thumb->getMedia()->filename);
        $this->assertEquals('gallery1', $gallery->getMedia()->filename);
    }

    public function test_picture_element_output(): void
    {
        $post = Post::create(['title' => 'Picture Post']);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'hero',
            'mime' => 'image/jpeg',
            'original_filename' => 'hero.jpg',
            'filename' => 'hero123',
            'position' => 'left',
        ]);

        $picture = (string) $post->img('hero')->picture();

        $this->assertStringContainsString('<picture>', $picture);
        $this->assertStringContainsString('</picture>', $picture);
        $this->assertStringContainsString('<source', $picture);
        $this->assertStringContainsString('<img', $picture);
    }

    public function test_background_style_output(): void
    {
        $post = Post::create(['title' => 'Background Post']);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'background',
            'mime' => 'image/jpeg',
            'original_filename' => 'bg.jpg',
            'filename' => 'bg123',
            'position' => 'left',
        ]);

        $style = $post->img('background')->background();

        $this->assertStringContainsString('background-image:', $style);
        $this->assertStringContainsString('url(', $style);
    }

    public function test_to_string_returns_url(): void
    {
        $post = Post::create(['title' => 'String Post']);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'test.jpg',
            'filename' => 'test123',
            'position' => 'left',
        ]);

        $url = (string) $post->img('cover');

        $this->assertIsString($url);
        $this->assertNotEmpty($url);
    }
}
