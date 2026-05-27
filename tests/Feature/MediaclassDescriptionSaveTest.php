<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
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
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('updated_count', 1);

        $this->assertSame([
            'en' => 'Updated English',
            'fr' => 'Description française',
        ], $media->refresh()->description);
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

    public function test_uploadable_component_renders_translated_description_save_button(): void
    {
        $view = file_get_contents(__DIR__ . '/../../resources/views/components/uploadable.blade.php');
        $script = file_get_contents(__DIR__ . '/../../public/vendor/mfw-mediaclass/uploader.js');

        $this->assertStringContainsString("data-ajax=\"{{ route('mediaclass.ajax') }}\"", $view);
        $this->assertStringContainsString('mediaclass-save-descriptions', $view);
        $this->assertStringContainsString("__('mfw-mediaclass.buttons.save_descriptions')", $view);
        $this->assertStringContainsString("{name: 'action', value: 'saveDescriptions'}", $script);
        $this->assertStringContainsString('mfwAjax(formData, uploadable', $script);
    }
}
