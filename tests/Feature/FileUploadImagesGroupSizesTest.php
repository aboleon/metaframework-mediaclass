<?php

namespace MetaFramework\Mediaclass\Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use MetaFramework\Mediaclass\Support\Path;
use MetaFramework\Mediaclass\Tests\Fixtures\PostWithGroupSizes;
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
}
