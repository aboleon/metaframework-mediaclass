<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use MetaFramework\Mediaclass\Components\Stored;
use MetaFramework\Mediaclass\Support\BridgeMedia;
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

        $this->assertSame([
            [
                'bridgeId' => 'legacy:1',
                'group' => 'cover',
                'subgroup' => null,
            ],
        ], PostWithBridgeMedia::$deletedBridgeMedia);
    }
}
