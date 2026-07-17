<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Feature;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use MetaFramework\Mediaclass\Components\Stored;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Tests\Fixtures\Post;
use MetaFramework\Mediaclass\Tests\TestCase;

class StoredVideoComponentTest extends TestCase
{
    public function test_direct_video_with_cdn_suffix_uses_lightgallery_html5_video_data(): void
    {
        $url = 'https://video.bta.bg/2026/06/05/0000.260605_VIDIN_RAZKOPKI_NEW.mp4_up';
        $post = Post::create(['title' => 'Direct video']);

        Media::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'group' => 'image_after',
            'mime' => 'video/url',
            'original_filename' => $url,
            'filename' => 'direct-video',
            'position' => 'left',
            'storable' => [
                'url' => $url,
                'embed_width' => '100%',
                'embed_height' => 315,
            ],
        ]);

        View::share('errors', new ViewErrorBag);
        $component = new Stored(model: $post->load('media'), group: 'image_after');
        $html = $component->resolveView()->with($component->data())->render();

        $this->assertStringContainsString('data-video=', $html);
        $this->assertStringContainsString('video\\/mp4', $html);
        $this->assertStringContainsString('0000.260605_VIDIN_RAZKOPKI_NEW.mp4_up', $html);
        $this->assertStringNotContainsString('data-src="' . $url . '"', $html);
    }
}
