<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use MetaFramework\Mediaclass\Components\Uploadable;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Tests\Fixtures\Post;
use MetaFramework\Mediaclass\Tests\TestCase;

class MediaclassSubgroupTest extends TestCase
{
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('mfw-mediaclass.subgroups', [
            'count' => 3,
            'label' => 'Group',
            'empty_label' => 'Normal flow',
            'key_prefix' => 'group_',
            'groups' => [
                'gallery' => true,
            ],
        ]);
    }

    public function test_uploadable_component_exposes_dynamic_subgroup_configuration(): void
    {
        $post = Post::query()->create(['title' => 'Gallery']);
        $media = $this->media($post, 'gallery', 'group_2');
        $uploadable = new Uploadable(model: $post->load('media'), group: 'gallery');
        $view = file_get_contents(__DIR__ . '/../../resources/views/components/uploadable.blade.php');

        $this->assertSame([
            'group_1' => 'Group 1',
            'group_2' => 'Group 2',
            'group_3' => 'Group 3',
        ], $uploadable->subgroupOptions);
        $this->assertSame([$media->id => 'group_2'], $uploadable->subgroupValues);
        $this->assertStringContainsString('data-subgroup-options=', $view);
        $this->assertStringContainsString('data-mediaclass-subgroup-select', $view);
        $this->assertStringContainsString("action: 'saveSubgroup'", $view);
        $this->assertStringContainsString('mediaclass:subgroup-saved', $view);
    }

    public function test_ajax_can_save_and_clear_native_media_subgroup(): void
    {
        $post = Post::query()->create(['title' => 'Gallery']);
        $media = $this->media($post, 'gallery');

        $response = $this->post('mediaclass-ajax', [
            'action' => 'saveSubgroup',
            'model' => Post::class,
            'model_id' => $post->id,
            'group' => 'gallery',
            'media_id' => $media->id,
            'subgroup' => 'group_2',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('media_id', $media->id);
        $response->assertJsonPath('group', 'gallery');
        $response->assertJsonPath('subgroup', 'group_2');
        $response->assertJsonPath('uses_subgroups', true);
        $this->assertSame('group_2', $media->refresh()->subgroup);

        $response = $this->post('mediaclass-ajax', [
            'action' => 'saveSubgroup',
            'model' => Post::class,
            'model_id' => $post->id,
            'group' => 'gallery',
            'media_id' => $media->id,
            'subgroup' => '',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('subgroup', null);
        $response->assertJsonPath('uses_subgroups', false);
        $this->assertNull($media->refresh()->subgroup);
    }

    public function test_ajax_rejects_invalid_subgroup_value(): void
    {
        $post = Post::query()->create(['title' => 'Gallery']);
        $media = $this->media($post, 'gallery');

        $response = $this->post('mediaclass-ajax', [
            'action' => 'saveSubgroup',
            'model' => Post::class,
            'model_id' => $post->id,
            'group' => 'gallery',
            'media_id' => $media->id,
            'subgroup' => 'group_99',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', true);
        $this->assertNull($media->refresh()->subgroup);
    }

    public function test_ajax_rejects_subgroup_save_for_another_model_record(): void
    {
        $post = Post::query()->create(['title' => 'Gallery']);
        $otherPost = Post::query()->create(['title' => 'Other']);
        $media = $this->media($otherPost, 'gallery');

        $response = $this->post('mediaclass-ajax', [
            'action' => 'saveSubgroup',
            'model' => Post::class,
            'model_id' => $post->id,
            'group' => 'gallery',
            'media_id' => $media->id,
            'subgroup' => 'group_1',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('error', true);
        $this->assertNull($media->refresh()->subgroup);
    }

    private function media(Post $post, string $group, ?string $subgroup = null): Media
    {
        return Media::query()->create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => $group,
            'subgroup' => $subgroup,
            'mime' => 'image/jpeg',
            'original_filename' => 'gallery.jpg',
            'filename' => 'gallery',
            'position' => 'left',
        ]);
    }
}
