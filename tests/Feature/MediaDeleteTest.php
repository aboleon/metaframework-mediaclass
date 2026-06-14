<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Storage;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Support\Path;
use MetaFramework\Mediaclass\Tests\Fixtures\PostWithCustomMediaPath;
use MetaFramework\Mediaclass\Tests\TestCase;

class MediaDeleteTest extends TestCase
{
    use WithoutMiddleware;

    public function test_delete_removes_only_the_requested_models_media_and_exact_files(): void
    {
        $post = PostWithCustomMediaPath::create(['title' => 'Owner']);
        $otherPost = PostWithCustomMediaPath::create(['title' => 'Other owner']);
        $target = $this->media($post, 'abc123');
        $sibling = $this->media($post, 'abc123x');
        $other = $this->media($otherPost, 'other1');

        foreach (['xl', 'sm'] as $size) {
            Storage::disk('testing')->put(Path::mediaFilePathForMedia($target, $size), 'target');
            Storage::disk('testing')->put(Path::mediaFilePathForMedia($sibling, $size), 'sibling');
            Storage::disk('testing')->put(Path::mediaFilePathForMedia($other, $size), 'other');
        }

        $response = $this->post('mediaclass-ajax', [
            'action' => 'delete',
            'id' => $target->id,
            'model' => PostWithCustomMediaPath::class,
            'model_id' => $post->id,
            'group' => 'cover',
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('deleted_id', $target->id);

        $this->assertModelMissing($target);
        $this->assertModelExists($sibling);
        $this->assertModelExists($other);

        foreach (['xl', 'sm'] as $size) {
            Storage::disk('testing')->assertMissing(Path::mediaFilePathForMedia($target, $size));
            Storage::disk('testing')->assertExists(Path::mediaFilePathForMedia($sibling, $size));
            Storage::disk('testing')->assertExists(Path::mediaFilePathForMedia($other, $size));
        }
    }

    public function test_delete_rejects_a_media_record_owned_by_another_model(): void
    {
        $post = PostWithCustomMediaPath::create(['title' => 'Owner']);
        $otherPost = PostWithCustomMediaPath::create(['title' => 'Other owner']);
        $other = $this->media($otherPost, 'other1');
        $path = Path::mediaFilePathForMedia($other, 'xl');

        Storage::disk('testing')->put($path, 'other');

        $response = $this->post('mediaclass-ajax', [
            'action' => 'delete',
            'id' => $other->id,
            'model' => PostWithCustomMediaPath::class,
            'model_id' => $post->id,
            'group' => 'cover',
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', true);
        $response->assertJsonMissingPath('deleted_id');

        $this->assertModelExists($other);
        Storage::disk('testing')->assertExists($path);
    }

    public function test_delete_requires_the_owning_model_id(): void
    {
        $post = PostWithCustomMediaPath::create(['title' => 'Owner']);
        $media = $this->media($post, 'target1');

        $response = $this->post('mediaclass-ajax', [
            'action' => 'delete',
            'id' => $media->id,
            'model' => PostWithCustomMediaPath::class,
            'group' => 'cover',
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', true);

        $this->assertModelExists($media);
    }

    private function media(PostWithCustomMediaPath $post, string $filename): Media
    {
        $media = Media::query()->create([
            'model_type' => PostWithCustomMediaPath::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => $filename . '.jpg',
            'filename' => $filename,
            'position' => 'left',
        ]);
        $media->setRelation('model', $post);

        return $media;
    }
}
