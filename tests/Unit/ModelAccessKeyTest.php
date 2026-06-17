<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Unit;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Models\ModelKey;
use MetaFramework\Mediaclass\Support\ModelAccessKey;
use MetaFramework\Mediaclass\Support\Path;
use MetaFramework\Mediaclass\Tests\Fixtures\Post;
use MetaFramework\Mediaclass\Tests\TestCase;

class ModelAccessKeyTest extends TestCase
{
    public function test_preloaded_models_do_not_query_access_key_per_model(): void
    {
        $posts = $this->postsWithAccessKeys();

        ModelAccessKey::flushCache();
        DB::enableQueryLog();

        ModelAccessKey::preloadForModels($posts);

        $accessKeys = $posts
            ->map(fn (Post $post): ?string => ModelAccessKey::forModel($post, create: false))
            ->all();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(['model-key-1', 'model-key-2', 'model-key-3'], $accessKeys);
        $this->assertSame(1, $this->accessKeyTableSelectCount($queries));
    }

    public function test_preloaded_media_do_not_query_access_key_per_media(): void
    {
        $posts = $this->postsWithAccessKeys();
        $mediaItems = $posts->map(fn (Post $post, int $index): Media => Media::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'image-' . ($index + 1) . '.jpg',
            'filename' => 'img' . ($index + 1),
            'position' => 'left',
        ]));

        ModelAccessKey::flushCache();
        DB::enableQueryLog();

        ModelAccessKey::preloadForMedia($mediaItems);

        $folders = $mediaItems
            ->map(fn (Media $media): string => Path::mediaFolderForMedia($media))
            ->all();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(['model-key-1', 'model-key-2', 'model-key-3'], $folders);
        $this->assertSame(1, $this->accessKeyTableSelectCount($queries));
    }

    public function test_media_folder_resolution_does_not_create_access_key_by_default(): void
    {
        $post = Post::create(['title' => 'Read Only Path']);

        $this->assertSame('post/' . $post->id, Path::mediaFolderName($post));
        $this->assertDatabaseMissing('mediaclass_model_keys', [
            'model_type' => Post::class,
            'model_id' => $post->id,
        ]);

        $this->assertNotNull(ModelAccessKey::forModel($post, create: true));
        $this->assertDatabaseHas('mediaclass_model_keys', [
            'model_type' => Post::class,
            'model_id' => $post->id,
        ]);
    }

    /**
     * @return Collection<int, Post>
     */
    private function postsWithAccessKeys(): Collection
    {
        return collect(range(1, 3))
            ->map(function (int $number): Post {
                $post = Post::create(['title' => 'Post ' . $number]);

                ModelKey::create([
                    'model_type' => Post::class,
                    'model_id' => $post->id,
                    'access_key' => 'model-key-' . $number,
                ]);

                return $post;
            });
    }

    /**
     * @param  array<int, array{query: string}>  $queries
     */
    private function accessKeyTableSelectCount(array $queries): int
    {
        return collect($queries)
            ->filter(function (array $query): bool {
                $sql = strtolower($query['query']);

                return str_contains($sql, 'select')
                    && (
                        str_contains($sql, 'from "mediaclass_model_keys"')
                        || str_contains($sql, 'from `mediaclass_model_keys`')
                    );
            })
            ->count();
    }
}
