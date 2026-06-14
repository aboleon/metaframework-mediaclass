<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use MetaFramework\Mediaclass\Components\Stored;
use MetaFramework\Mediaclass\Components\Uploadable;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Support\BridgeMedia;
use MetaFramework\Mediaclass\Tests\Fixtures\Post;
use MetaFramework\Mediaclass\Tests\Fixtures\PostWithBridgeMedia;
use MetaFramework\Mediaclass\Tests\TestCase;

class BridgeMediaTest extends TestCase
{
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        PostWithBridgeMedia::resetBridgeState();
    }

    public function test_stored_component_merges_bridge_media_from_model(): void
    {
        $post = PostWithBridgeMedia::create(['title' => 'Bridge']);

        $component = new Stored(model: $post, group: 'cover');

        $this->assertCount(1, $component->medias);
        $this->assertInstanceOf(BridgeMedia::class, $component->medias->first());
        $this->assertSame('legacy:1', $component->medias->first()->id);
    }

    public function test_stored_component_can_mix_native_and_bridge_media(): void
    {
        $post = PostWithBridgeMedia::create(['title' => 'Bridge']);

        Media::query()->create([
            'model_type' => PostWithBridgeMedia::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'native.jpg',
            'filename' => 'native',
            'position' => 'left',
        ]);

        $component = new Stored(model: $post->load('media'), group: 'cover');

        $this->assertCount(2, $component->medias);
        $this->assertInstanceOf(Media::class, $component->medias->first());
        $this->assertInstanceOf(BridgeMedia::class, $component->medias->last());
    }

    public function test_stored_component_renders_one_lightgallery_item_with_thumbnail_source_per_image(): void
    {
        $post = Post::create(['title' => 'Gallery']);

        Media::query()->create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'native.jpg',
            'filename' => 'native',
            'position' => 'left',
        ]);

        View::share('errors', new ViewErrorBag);

        $html = Blade::render(
            '<x-mediaclass::stored :model="$post" group="cover" :description="false" />',
            ['post' => $post->load('media')],
        );

        $this->assertSame(1, substr_count($html, 'class="lightgallery-item d-block w-100 h-100"'));
        $this->assertStringContainsString('data-thumb=', $html);
        $this->assertStringNotContainsString('class="lightgallery-item zoom"', $html);
    }

    public function test_uploadable_component_accepts_grid_layout(): void
    {
        $post = PostWithBridgeMedia::create(['title' => 'Bridge']);

        $uploadable = new Uploadable(model: $post, group: 'cover', grid: 3);
        $stored = new Stored(model: $post, group: 'cover', grid: 3);

        $this->assertSame(3, $uploadable->grid);
        $this->assertSame(3, $stored->grid);
    }

    public function test_uploadable_component_clamps_grid_layout(): void
    {
        $post = PostWithBridgeMedia::create(['title' => 'Bridge']);

        $uploadable = new Uploadable(model: $post, group: 'cover', grid: 8);
        $stored = new Stored(model: $post, group: 'cover', grid: 0);

        $this->assertSame(4, $uploadable->grid);
        $this->assertSame(1, $stored->grid);
    }

    public function test_process_media_forwards_bridge_payload_to_model(): void
    {
        $post = PostWithBridgeMedia::create(['title' => 'Bridge']);

        request()->merge([
            'mediaclass_bridge' => [
                'cover' => [
                    'legacy_1' => [
                        'id' => 'legacy:1',
                        'description' => ['en' => 'Updated legacy image'],
                    ],
                ],
            ],
        ]);

        $post->processMedia();

        $this->assertSame('legacy:1', PostWithBridgeMedia::$syncedBridgeMedia['cover']['legacy_1']['id']);
        $this->assertSame('Updated legacy image', PostWithBridgeMedia::$syncedBridgeMedia['cover']['legacy_1']['description']['en']);
    }

    public function test_delete_bridge_media_delegates_to_model(): void
    {
        $post = PostWithBridgeMedia::create(['title' => 'Bridge']);

        $response = $this->post('mediaclass-ajax', [
            'action' => 'deleteBridge',
            'model' => PostWithBridgeMedia::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'id' => 'legacy:1',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('deleted_id', 'legacy:1');

        $this->assertSame([
            [
                'bridgeId' => 'legacy:1',
                'group' => 'cover',
                'subgroup' => null,
            ],
        ], PostWithBridgeMedia::$deletedBridgeMedia);
    }
}
