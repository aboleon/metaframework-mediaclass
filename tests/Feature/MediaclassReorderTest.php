<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Tests\Fixtures\Post;
use MetaFramework\Mediaclass\Tests\TestCase;

class MediaclassReorderTest extends TestCase
{
    use WithoutMiddleware;

    public function test_reordering_media_persists_left_to_right_order_and_resets_flow_assignments(): void
    {
        $post = Post::query()->create(['title' => 'Gallery']);
        $first = $this->media($post, 1, 'right', 'group_1');
        $second = $this->media($post, 2, 'up', null);
        $third = $this->media($post, 3, 'down', 'group_2');

        $response = $this->post('mediaclass-ajax', [
            'action' => 'reorder',
            'model' => Post::class,
            'model_id' => $post->id,
            'group' => 'gallery',
            'media_ids' => [$third->id, $first->id, $second->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('changed', true);
        $response->assertJsonPath('media_ids', [$third->id, $first->id, $second->id]);
        $response->assertJsonPath('uses_subgroups', false);

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            Media::query()
                ->where('model_id', $post->id)
                ->where('group', 'gallery')
                ->orderBy('sort_order')
                ->pluck('id')
                ->all(),
        );

        Media::query()
            ->where('model_id', $post->id)
            ->where('group', 'gallery')
            ->get()
            ->each(function (Media $media): void {
                $this->assertSame('left', $media->position);
                $this->assertNull($media->subgroup);
            });
    }

    public function test_submitting_the_existing_order_does_not_reset_flow_assignments(): void
    {
        $post = Post::query()->create(['title' => 'Gallery']);
        $first = $this->media($post, 1, 'right', 'group_1');
        $second = $this->media($post, 2, 'down', null);

        $response = $this->post('mediaclass-ajax', [
            'action' => 'reorder',
            'model' => Post::class,
            'model_id' => $post->id,
            'group' => 'gallery',
            'media_ids' => [$first->id, $second->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('changed', false);
        $this->assertSame('right', $first->refresh()->position);
        $this->assertSame('group_1', $first->subgroup);
        $this->assertSame('down', $second->refresh()->position);
    }

    public function test_reordering_rejects_partial_or_foreign_media_sets(): void
    {
        $post = Post::query()->create(['title' => 'Gallery']);
        $otherPost = Post::query()->create(['title' => 'Other']);
        $first = $this->media($post, 1, 'right', 'group_1');
        $second = $this->media($post, 2, 'down', null);
        $foreign = $this->media($otherPost, 1, 'left', null);

        foreach ([[$second->id], [$second->id, $foreign->id]] as $mediaIds) {
            $response = $this->post('mediaclass-ajax', [
                'action' => 'reorder',
                'model' => Post::class,
                'model_id' => $post->id,
                'group' => 'gallery',
                'media_ids' => $mediaIds,
            ]);

            $response->assertStatus(200);
            $response->assertJsonPath('error', true);
        }

        $this->assertSame(1, $first->refresh()->sort_order);
        $this->assertSame('right', $first->position);
        $this->assertSame('group_1', $first->subgroup);
        $this->assertSame(2, $second->refresh()->sort_order);
    }

    private function media(
        Post $post,
        int $sortOrder,
        string $position,
        ?string $subgroup,
    ): Media {
        return Media::query()->create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'gallery',
            'subgroup' => $subgroup,
            'mime' => 'image/jpeg',
            'original_filename' => "gallery-{$sortOrder}.jpg",
            'filename' => "sort{$sortOrder}",
            'position' => $position,
            'sort_order' => $sortOrder,
        ]);
    }
}
