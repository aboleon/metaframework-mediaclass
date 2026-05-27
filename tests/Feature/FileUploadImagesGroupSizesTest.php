<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Support\Path;
use MetaFramework\Mediaclass\Tests\Fixtures\PostWithCustomMediaPath;
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

    public function test_upload_resizes_configured_sizes_by_width(): void
    {
        $post = PostWithGroupSizes::create(['title' => 'Sized Upload']);

        $file = UploadedFile::fake()->image('cover.jpg', 1600, 1000);

        $response = $this->post('mediaclass-ajax', [
            'action' => 'upload',
            'model' => PostWithGroupSizes::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'files' => [$file],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', null);

        $folder = Path::mediaFolderName($post);
        $files = Storage::disk('testing')->files($folder);
        $xlPath = collect($files)->first(fn ($path) => str_contains($path, '/1600_'));
        $smPath = collect($files)->first(fn ($path) => str_contains($path, '/1200_'));

        $this->assertNotNull($xlPath);
        $this->assertNotNull($smPath);

        $this->assertSame([1600, 1000], array_slice(getimagesize(Storage::disk('testing')->path($xlPath)), 0, 2));
        $this->assertSame([1200, 750], array_slice(getimagesize(Storage::disk('testing')->path($smPath)), 0, 2));
    }

    public function test_upload_can_use_model_defined_folder_and_size_filenames(): void
    {
        $post = PostWithCustomMediaPath::create(['title' => 'Custom Path Upload']);

        $file = UploadedFile::fake()->image('cover.jpg', 1600, 900);

        $response = $this->post('mediaclass-ajax', [
            'action' => 'upload',
            'model' => PostWithCustomMediaPath::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'files' => [$file],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', null);

        $media = Media::query()->where('model_type', PostWithCustomMediaPath::class)->firstOrFail();
        $media->setRelation('model', $post);

        $this->assertSame('custom-key', Path::mediaFolderForMedia($media));
        $this->assertSame('custom-key/' . $media->filename . '_xl.jpg', Path::mediaFilePathForMedia($media, 'xl'));
        $this->assertSame('custom-key/' . $media->filename . '_sm.jpg', Path::mediaFilePathForMedia($media, 'sm'));

        $files = Storage::disk('testing')->files('custom-key');

        $this->assertContains('custom-key/' . $media->filename . '_xl.jpg', $files);
        $this->assertContains('custom-key/' . $media->filename . '_sm.jpg', $files);
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
