<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MetaFramework\Mediaclass\Tests\Fixtures\Post;
use MetaFramework\Mediaclass\Tests\TestCase;

class MediaclassSortOrderMigrationTest extends TestCase
{
    public function test_migration_backfills_existing_flow_order_per_media_group(): void
    {
        $post = Post::query()->create(['title' => 'Gallery']);

        foreach ([
            ['filename' => 'up0001', 'position' => 'up'],
            ['filename' => 'left01', 'position' => 'left'],
            ['filename' => 'down01', 'position' => 'down'],
            ['filename' => 'right1', 'position' => 'right'],
        ] as $media) {
            DB::table('mediaclass')->insert([
                'model_type' => Post::class,
                'model_id' => $post->id,
                'group' => 'gallery',
                'position' => $media['position'],
                'sort_order' => 0,
                'mime' => 'image/jpeg',
                'original_filename' => $media['filename'] . '.jpg',
                'filename' => $media['filename'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('mediaclass', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });

        $migration = require __DIR__ . '/../../database/migrations/add_sort_order_to_mediaclass.php';
        $migration->up();

        $this->assertSame(
            ['left01', 'right1', 'up0001', 'down01'],
            DB::table('mediaclass')
                ->where('model_id', $post->id)
                ->where('group', 'gallery')
                ->orderBy('sort_order')
                ->pluck('filename')
                ->all(),
        );
    }
}
