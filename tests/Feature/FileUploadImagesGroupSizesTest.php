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
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

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

    #[RunInSeparateProcess]
    public function test_upload_processes_large_source_images_without_cloning_original_bitmap(): void
    {
        $fixture = dirname(__DIR__, 5) . '/bg/_doc/pink-roses.jpg';

        if (!is_file($fixture)) {
            $this->markTestSkipped('The local large image fixture is not available.');
        }

        $post = PostWithGroupSizes::create(['title' => 'Large Source Upload']);

        $file = new UploadedFile($fixture, 'pink-roses.jpg', 'image/jpeg', null, true);

        $response = $this->post('mediaclass-ajax', [
            'action' => 'upload',
            'model' => PostWithGroupSizes::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'files' => [$file],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('uploaded.original_filename', 'pink-roses.jpg');

        $folder = Path::mediaFolderName($post);
        $files = Storage::disk('testing')->files($folder);
        $xlPath = collect($files)->first(fn ($path) => str_contains($path, '/1600_'));
        $smPath = collect($files)->first(fn ($path) => str_contains($path, '/1200_'));

        $this->assertCount(2, $files);
        $this->assertNotNull($xlPath);
        $this->assertNotNull($smPath);
        $this->assertSame([1600, 900], array_slice(getimagesize(Storage::disk('testing')->path($xlPath)), 0, 2));
        $this->assertSame([1200, 675], array_slice(getimagesize(Storage::disk('testing')->path($smPath)), 0, 2));
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

        $this->assertDatabaseHas('mediaclass_model_keys', [
            'model_type' => PostWithCustomMediaPath::class,
            'model_id' => $post->id,
            'access_key' => 'custom-key',
        ]);
        $this->assertSame('custom-key', Path::mediaFolderForMedia($media));

        $post->mediaFolder = 'changed-key';

        $this->assertSame('custom-key', Path::mediaFolderForMedia($media));
        $this->assertSame('custom-key/' . $media->filename . '_xl.jpg', Path::mediaFilePathForMedia($media, 'xl'));
        $this->assertSame('custom-key/' . $media->filename . '_sm.jpg', Path::mediaFilePathForMedia($media, 'sm'));

        $files = Storage::disk('testing')->files('custom-key');

        $this->assertContains('custom-key/' . $media->filename . '_xl.jpg', $files);
        $this->assertContains('custom-key/' . $media->filename . '_sm.jpg', $files);
    }

    public function test_custom_media_paths_resolve_missing_preview_size_to_existing_group_size(): void
    {
        $post = PostWithCustomMediaPath::create(['title' => 'Custom Path Preview']);
        $media = Media::create([
            'model_type' => PostWithCustomMediaPath::class,
            'model_id' => $post->id,
            'group' => 'banner',
            'mime' => 'image/jpeg',
            'original_filename' => 'banner.jpg',
            'filename' => 'custom123',
            'position' => 'left',
        ]);
        $media->setRelation('model', $post);

        Storage::disk('testing')->put('custom-key/custom123_main.jpg', 'test');

        $this->assertSame('main', $media->resolveSizeKey('sm'));
        $this->assertSame('custom-key/custom123_main.jpg', Path::mediaFilePathForMedia($media, 'sm'));
        $this->assertStringContainsString('custom-key/custom123_main.jpg', $media->url('sm'));
        $this->assertStringNotContainsString('custom123_sm.jpg', $media->url('sm'));
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

    public function test_upload_returns_json_error_when_file_payload_is_missing(): void
    {
        $post = PostWithGroupSizes::create(['title' => 'Missing File Upload']);

        $response = $this->post('mediaclass-ajax', [
            'action' => 'upload',
            'model' => PostWithGroupSizes::class,
            'model_id' => $post->id,
            'group' => 'cover',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', true);
    }

    public function test_upload_warns_when_smaller_than_largest_group_size_and_dimensions_are_not_enforced(): void
    {
        $post = PostWithGroupSizes::create(['title' => 'Advisory Sized Upload']);

        $file = UploadedFile::fake()->image('cover.jpg', 800, 400);

        $response = $this->post('mediaclass-ajax', [
            'action' => 'upload',
            'model' => PostWithGroupSizes::class,
            'model_id' => $post->id,
            'group' => 'advisory_cover',
            'files' => [$file],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('mfw_ajax_messages.0.warning', function ($value) {
            return is_string($value) && str_contains($value, '1600 x 900');
        });

        $this->assertDatabaseHas('mediaclass', [
            'model_type' => PostWithGroupSizes::class,
            'model_id' => $post->id,
            'group' => 'advisory_cover',
        ]);
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
            'embed_width_mode' => 'full',
            'embed_height' => 420,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('filetype', 'video');
        $response->assertJsonPath('link', $url);
        $response->assertJsonPath('preview', 'https://i.ytimg.com/vi/abc123/hqdefault.jpg');

        $media = Media::query()->where('group', 'gallery')->firstOrFail();

        $this->assertSame('video/url', $media->mime);
        $this->assertSame($url, $media->original_filename);
        $this->assertSame($url, $media->storable['url']);
        $this->assertSame('100%', $media->storable['embed_width']);
        $this->assertSame(420, $media->storable['embed_height']);
        $this->assertSame(
            'https://i.ytimg.com/vi/abc123/hqdefault.jpg',
            $media->storable['thumbnail_url'],
        );
        $this->assertSame($url, $media->url());
    }

    public function test_upload_url_uses_default_video_dimensions(): void
    {
        $post = PostWithGroupSizes::create(['title' => 'Default Video Dimensions']);

        $this->post('mediaclass-ajax', [
            'action' => 'uploadUrl',
            'model' => PostWithGroupSizes::class,
            'model_id' => $post->id,
            'group' => 'gallery',
            'url' => 'https://www.youtube.com/watch?v=default123',
        ])->assertJsonPath('error', null);

        $media = Media::query()->where('group', 'gallery')->firstOrFail();

        $this->assertSame(560, $media->storable['embed_width']);
        $this->assertSame(315, $media->storable['embed_height']);
        $this->assertSame(
            'https://i.ytimg.com/vi/default123/hqdefault.jpg',
            $media->storable['thumbnail_url'],
        );
    }
}
