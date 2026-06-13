<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Tests\Fixtures\Post;
use MetaFramework\Mediaclass\Tests\Fixtures\PostWithBridgeMedia;
use MetaFramework\Mediaclass\Tests\TestCase;

class MediaclassDescriptionSaveTest extends TestCase
{
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        PostWithBridgeMedia::resetBridgeState();
    }

    public function test_ajax_can_save_native_media_descriptions_for_current_uploadable_group(): void
    {
        $post = Post::create(['title' => 'Descriptions']);
        $media = Media::query()->create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'cover.jpg',
            'filename' => 'cover',
            'position' => 'left',
            'description' => ['en' => 'Old description'],
        ]);
        $subgroupedMedia = Media::query()->create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'subgroup' => 'group_1',
            'mime' => 'image/jpeg',
            'original_filename' => 'grouped.jpg',
            'filename' => 'grouped',
            'position' => 'left',
            'description' => ['en' => 'Old grouped description'],
        ]);
        $otherGroupMedia = Media::query()->create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'gallery',
            'mime' => 'image/jpeg',
            'original_filename' => 'gallery.jpg',
            'filename' => 'gallery',
            'position' => 'left',
            'description' => ['en' => 'Keep me'],
        ]);
        $positionOnlyMedia = Media::query()->create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'position.jpg',
            'filename' => 'position',
            'position' => 'left',
            'description' => ['en' => 'Do not clear'],
        ]);

        $response = $this->post('mediaclass-ajax', [
            'action' => 'saveDescriptions',
            'model' => Post::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mediaclass' => [
                $media->id => [
                    'description' => [
                        'en' => ' Updated English ',
                        'fr' => 'Description française',
                    ],
                ],
                $otherGroupMedia->id => [
                    'description' => [
                        'en' => 'Wrong group',
                    ],
                ],
                $positionOnlyMedia->id => [
                    'position' => 'right',
                ],
                $subgroupedMedia->id => [
                    'description' => [
                        'en' => 'Updated grouped English',
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('updated_count', 2);

        $this->assertSame([
            'en' => 'Updated English',
            'fr' => 'Description française',
        ], $media->refresh()->description);
        $this->assertSame(['en' => 'Updated grouped English'], $subgroupedMedia->refresh()->description);
        $this->assertSame(['en' => 'Keep me'], $otherGroupMedia->refresh()->description);
        $this->assertSame(['en' => 'Do not clear'], $positionOnlyMedia->refresh()->description);
    }

    public function test_ajax_delegates_bridge_media_descriptions_to_model_hook(): void
    {
        $post = PostWithBridgeMedia::create(['title' => 'Bridge']);

        $response = $this->post('mediaclass-ajax', [
            'action' => 'saveDescriptions',
            'model' => PostWithBridgeMedia::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mediaclass_bridge' => [
                'cover' => [
                    'legacy_1' => [
                        'id' => 'legacy:1',
                        'description' => [
                            'en' => 'Updated legacy image',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('updated_count', 1);

        $this->assertSame('legacy:1', PostWithBridgeMedia::$syncedBridgeMedia['cover']['legacy_1']['id']);
        $this->assertSame('Updated legacy image', PostWithBridgeMedia::$syncedBridgeMedia['cover']['legacy_1']['description']['en']);
    }

    public function test_ajax_can_save_native_video_dimensions(): void
    {
        $post = Post::create(['title' => 'Video dimensions']);
        $media = Media::query()->create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mime' => 'video/url',
            'original_filename' => 'https://youtu.be/abc123',
            'filename' => 'video',
            'position' => 'left',
            'storable' => [
                'url' => 'https://youtu.be/abc123',
                'embed_width' => 560,
                'embed_height' => 315,
            ],
        ]);

        $response = $this->post('mediaclass-ajax', [
            'action' => 'saveDescriptions',
            'model' => Post::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mediaclass' => [
                $media->id => [
                    'embed_width_mode' => 'full',
                    'embed_height' => 420,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('updated_count', 1);

        $this->assertSame([
            'url' => 'https://youtu.be/abc123',
            'embed_width' => '100%',
            'embed_height' => 420,
        ], $media->refresh()->storable);
    }

    public function test_uploadable_component_renders_translated_media_details_save_button(): void
    {
        $view = file_get_contents(__DIR__ . '/../../resources/views/components/uploadable.blade.php');
        $script = file_get_contents(__DIR__ . '/../../public/vendor/mfw-mediaclass/uploader.js');

        $this->assertStringContainsString("data-ajax=\"{{ route('mediaclass.ajax') }}\"", $view);
        $this->assertStringContainsString('mediaclass-save-descriptions', $view);
        $this->assertStringContainsString("__('mfw-mediaclass.buttons.save_media_details')", $view);
        $this->assertStringContainsString("{name: 'action', value: 'saveDescriptions'}", $script);
        $this->assertStringContainsString('mfwAjax(formData, uploadable', $script);
    }

    public function test_stored_video_renders_editable_default_embed_dimensions(): void
    {
        $post = Post::create(['title' => 'Video controls']);
        $media = Media::query()->create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mime' => 'video/url',
            'original_filename' => 'https://youtu.be/abc123',
            'filename' => 'video',
            'position' => 'left',
            'storable' => ['url' => 'https://youtu.be/abc123'],
        ]);

        View::share('errors', new ViewErrorBag);

        $html = Blade::render(
            '<x-mediaclass::stored :model="$post" group="cover" :description="false" />',
            ['post' => $post->load('media')],
        );

        $this->assertStringContainsString(
            'name="mediaclass[' . $media->id . '][embed_width_mode]"',
            $html,
        );
        $this->assertMatchesRegularExpression('/<option value="pixels"[^>]*selected/', $html);
        $this->assertMatchesRegularExpression('/name="mediaclass\[' . $media->id . '\]\[embed_width\]"[^>]*value="560"/', $html);
        $this->assertMatchesRegularExpression('/name="mediaclass\[' . $media->id . '\]\[embed_height\]"[^>]*value="315"/', $html);
        $this->assertStringContainsString('value="full"', $html);
    }
}
