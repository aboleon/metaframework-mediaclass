<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Support\Path;
use MetaFramework\Mediaclass\Tests\Fixtures\PostWithGroupSizes;
use MetaFramework\Mediaclass\Tests\Fixtures\PostWithoutInstanceModelMethod;
use MetaFramework\Mediaclass\Tests\TestCase;

class FileUploadImagesGroupSizesTest extends TestCase
{
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\Intervention\Image\ImageManager::class)) {
            $this->markTestSkipped('Intervention Image is not installed.');
        }
    }

    public function test_upload_uses_group_sizes_and_creates_files(): void
    {
        $post = PostWithGroupSizes::create(['title' => 'Sized Upload']);

        $file = UploadedFile::fake()->image('cover.jpg', 1600, 900);

        $response = $this->post('mediaclass-ajax', [
            'action' => 'upload',
            'model' => PostWithGroupSizes::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'files' => [$file],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('urls.xl', function ($value) {
            return is_string($value) && $value !== '';
        });
        $response->assertJsonPath('urls.sm', function ($value) {
            return is_string($value) && $value !== '';
        });

        $folder = Path::mediaFolderName($post);
        $files = Storage::disk('testing')->files($folder);

        $this->assertCount(2, $files);
        $this->assertTrue(collect($files)->contains(fn ($path) => str_contains($path, '/1600_')));
        $this->assertTrue(collect($files)->contains(fn ($path) => str_contains($path, '/1200_')));
    }

    public function test_upload_rejects_images_smaller_than_largest_group_size(): void
    {
        $post = PostWithGroupSizes::create(['title' => 'Sized Upload']);

        $file = UploadedFile::fake()->image('cover.jpg', 800, 400);

        $response = $this->post('mediaclass-ajax', [
            'action' => 'upload',
            'model' => PostWithGroupSizes::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'files' => [$file],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', true);
    }

    public function test_upload_supports_models_with_model_method_returning_self_without_instance_property(): void
    {
        $post = PostWithoutInstanceModelMethod::create(['title' => 'Self Model Upload']);

        $file = UploadedFile::fake()->image('cover.jpg', 1600, 900);

        $response = $this->post('mediaclass-ajax', [
            'action' => 'upload',
            'model' => PostWithoutInstanceModelMethod::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'files' => [$file],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('cropable_settings', '{"cover":[1600,900,"Cover"]}');

        $media = Media::query()->where('model_type', PostWithoutInstanceModelMethod::class)->firstOrFail();

        $this->assertSame($post->id, $media->model_id);
        $this->assertSame('cover', $media->group);
        $this->assertSame('image/jpeg', $media->mime);
    }

    public function test_upload_url_stores_external_video_url(): void
    {
        $post = PostWithGroupSizes::create(['title' => 'Video Upload']);
        $url = 'https://www.youtube.com/watch?v=abc123';

        $response = $this->post('mediaclass-ajax', [
            'action' => 'uploadUrl',
            'model' => PostWithGroupSizes::class,
            'model_id' => $post->id,
            'group' => 'gallery',
            'url' => $url,
            'description' => ['en' => 'Video description'],
            'positions' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('filetype', 'video');
        $response->assertJsonPath('link', $url);

        $media = Media::query()->where('group', 'gallery')->firstOrFail();

        $this->assertSame('video/url', $media->mime);
        $this->assertSame($url, $media->original_filename);
        $this->assertSame($url, $media->storable['url']);
        $this->assertSame($url, $media->url());
    }
}
