<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Unit;

use Cohensive\OEmbed\Embed;
use Cohensive\OEmbed\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use MetaFramework\Mediaclass\Mediaclass;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Tests\TestCase;
use MetaFramework\Mediaclass\VideoEmbedders\Tf1InfoEmbedProvider;
use RuntimeException;

class MediaclassEmbedTest extends TestCase
{
    public function test_it_renders_an_external_video_media_url(): void
    {
        $url = 'https://www.youtube.com/watch?v=abc123';
        $media = new Media([
            'mime' => 'video/url',
            'storable' => ['url' => $url],
        ]);
        $options = ['loading' => 'lazy'];
        $embed = $this->createMock(Embed::class);
        $embed->expects($this->once())
            ->method('html')
            ->with([
                'width' => 560,
                'height' => 'auto',
                'loading' => 'lazy',
            ])
            ->willReturn('<iframe src="https://www.youtube.com/embed/abc123"></iframe>');

        $factory = $this->createMock(Factory::class);
        $factory->expects($this->once())
            ->method('get')
            ->with($url)
            ->willReturn($embed);

        $html = (new Mediaclass($factory))->embed($media, $options);

        $this->assertInstanceOf(HtmlString::class, $html);
        $this->assertSame(
            '<iframe src="https://www.youtube.com/embed/abc123"></iframe>',
            $html->toHtml(),
        );
    }

    public function test_it_returns_empty_html_for_invalid_or_unsupported_urls(): void
    {
        $factory = $this->createMock(Factory::class);
        $factory->expects($this->once())
            ->method('get')
            ->with('https://example.com/video')
            ->willReturn(null);

        $mediaclass = new Mediaclass($factory);

        $this->assertSame('', $mediaclass->embed('javascript:alert(1)')->toHtml());
        $this->assertSame('', $mediaclass->embed('https://example.com/video')->toHtml());
    }

    public function test_it_sanitizes_embed_attributes(): void
    {
        $embed = $this->createMock(Embed::class);
        $embed->expects($this->once())
            ->method('html')
            ->with([
                'width' => 560,
                'height' => 'auto',
                'loading' => 'lazy',
                'title' => 'Video&quot; onload=&quot;alert(1)',
            ])
            ->willReturn('<iframe></iframe>');

        $factory = $this->createStub(Factory::class);
        $factory->method('get')->willReturn($embed);

        (new Mediaclass($factory))->embed('https://youtu.be/abc123', [
            'loading' => 'lazy',
            'title' => 'Video" onload="alert(1)',
            'onload' => 'alert(1)',
        ]);
    }

    public function test_it_does_not_break_rendering_when_the_provider_fails(): void
    {
        $factory = $this->createStub(Factory::class);
        $factory->method('get')->willThrowException(new RuntimeException('Provider unavailable'));

        $html = (new Mediaclass($factory))->embed('https://youtu.be/abc123');

        $this->assertSame('', $html->toHtml());
    }

    public function test_global_helper_uses_the_mediaclass_service(): void
    {
        $embed = $this->createStub(Embed::class);
        $embed->method('html')->willReturn('<video controls></video>');

        $factory = $this->createStub(Factory::class);
        $factory->method('get')->willReturn($embed);

        $this->app->instance('mediaclass', new Mediaclass($factory));

        $this->assertSame(
            '<video controls></video>',
            mediaclass_embed('https://cdn.example.com/video.mp4')->toHtml(),
        );
    }

    public function test_it_renders_direct_video_urls_with_the_real_oembed_factory(): void
    {
        $html = (new Mediaclass)->embed(
            'https://cdn.example.com/video.mp4',
            ['loading' => 'lazy'],
        )->toHtml();

        $this->assertStringContainsString('<video', $html);
        $this->assertStringContainsString('width="560"', $html);
        $this->assertStringContainsString('height="auto"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('https://cdn.example.com/video.mp4', $html);
    }

    public function test_it_renders_direct_video_urls_with_a_cdn_suffix(): void
    {
        $url = 'https://video.bta.bg/2026/06/05/0000.260605_VIDIN_RAZKOPKI_NEW.mp4_up';
        $factory = $this->createStub(Factory::class);
        $factory->method('get')->willReturn(null);

        $html = (new Mediaclass($factory))->embed($url, [
            'width' => '100%',
            'height' => 315,
            'loading' => 'lazy',
            'title' => 'Vidin "video"',
        ])->toHtml();

        $this->assertStringContainsString('<video controls width="100%" height="315" loading="lazy"', $html);
        $this->assertStringContainsString('title="Vidin &quot;video&quot;"', $html);
        $this->assertStringContainsString('<source src="' . $url . '" type="video/mp4">', $html);
    }

    public function test_it_renders_tf1_info_player_urls_with_the_package_provider(): void
    {
        $url = 'https://www.tf1info.fr/player/647975e0-402c-4608-84ad-c12a3bb63ede/';
        $media = new Media([
            'mime' => 'video/url',
            'storable' => [
                'url' => $url,
                'embed_width' => '100%',
                'embed_height' => 'auto',
            ],
        ]);
        $factory = $this->createMock(Factory::class);
        $factory->expects($this->once())
            ->method('get')
            ->with($url)
            ->willReturn(null);

        $html = (new Mediaclass($factory))->embed($media, [
            'loading' => 'lazy',
            'title' => 'Bulgarie "video"',
            'onload' => 'alert(1)',
        ])->toHtml();

        $this->assertSame(
            '<iframe src="' . $url . '" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" allowfullscreen width="100%" height="315" loading="lazy" title="Bulgarie &quot;video&quot;"></iframe>',
            $html,
        );
    }

    public function test_tf1_info_provider_only_accepts_canonical_player_urls(): void
    {
        $provider = new Tf1InfoEmbedProvider;
        $uuid = '647975e0-402c-4608-84ad-c12a3bb63ede';

        $this->assertTrue($provider->supports("https://www.tf1info.fr/player/{$uuid}/"));

        foreach ([
            "http://www.tf1info.fr/player/{$uuid}/",
            "https://tf1info.fr/player/{$uuid}/",
            "https://www.tf1info.fr.example.com/player/{$uuid}/",
            "https://www.tf1info.fr/player/{$uuid}/?autoplay=1",
            'https://www.tf1info.fr/player/not-a-uuid/',
            'https://www.tf1info.fr/voyages/videos/article.html',
        ] as $url) {
            $this->assertFalse($provider->supports($url), $url);
        }
    }

    public function test_service_provider_registers_tf1_info_support(): void
    {
        $url = 'https://www.tf1info.fr/player/647975e0-402c-4608-84ad-c12a3bb63ede/';

        $this->assertStringStartsWith(
            '<iframe src="' . $url . '"',
            $this->app->make(Mediaclass::class)->embed($url)->toHtml(),
        );
    }

    public function test_tf1_info_provider_rejects_lookalike_player_urls(): void
    {
        $url = 'https://www.tf1info.fr.example.com/player/647975e0-402c-4608-84ad-c12a3bb63ede/';
        $factory = $this->createMock(Factory::class);
        $factory->expects($this->once())
            ->method('get')
            ->with($url)
            ->willReturn(null);

        $this->assertSame('', (new Mediaclass($factory))->embed($url)->toHtml());
    }

    public function test_stored_video_dimensions_override_the_default_embed_size(): void
    {
        $url = 'https://www.youtube.com/watch?v=abc123';
        $media = new Media([
            'mime' => 'video/url',
            'storable' => [
                'url' => $url,
                'embed_width' => '100%',
                'embed_height' => 420,
            ],
        ]);
        $embed = $this->createMock(Embed::class);
        $embed->expects($this->once())
            ->method('html')
            ->with([
                'width' => '100%',
                'height' => 420,
            ])
            ->willReturn('<iframe width="100%" height="420"></iframe>');

        $factory = $this->createStub(Factory::class);
        $factory->method('get')->willReturn($embed);

        $html = (new Mediaclass($factory))->embed($media);

        $this->assertSame('<iframe width="100%" height="420"></iframe>', $html->toHtml());
    }

    public function test_it_uses_a_stored_video_thumbnail_without_calling_the_provider(): void
    {
        $factory = $this->createMock(Factory::class);
        $factory->expects($this->never())->method('get');
        $media = new Media([
            'mime' => 'video/url',
            'storable' => [
                'url' => 'https://vimeo.com/123456789',
                'thumbnail_url' => 'https://cdn.example.com/poster.jpg',
            ],
        ]);

        $this->assertSame(
            'https://cdn.example.com/poster.jpg',
            (new Mediaclass($factory))->thumbnail($media),
        );
    }

    public function test_it_derives_youtube_thumbnails_without_calling_the_provider(): void
    {
        $factory = $this->createMock(Factory::class);
        $factory->expects($this->never())->method('get');
        $mediaclass = new Mediaclass($factory);

        $this->assertSame(
            'https://i.ytimg.com/vi/abc123/hqdefault.jpg',
            $mediaclass->thumbnail('https://www.youtube.com/watch?v=abc123'),
        );
        $this->assertSame(
            'https://i.ytimg.com/vi/xyz789/hqdefault.jpg',
            $mediaclass->thumbnail('https://youtu.be/xyz789'),
        );
    }

    public function test_it_fetches_and_caches_provider_thumbnails(): void
    {
        Cache::flush();

        $url = 'https://vimeo.com/987654321';
        $embed = $this->createStub(Embed::class);
        $embed->method('thumbnailUrl')->willReturn('https://cdn.example.com/vimeo-poster.jpg');

        $factory = $this->createMock(Factory::class);
        $factory->expects($this->once())
            ->method('get')
            ->with($url)
            ->willReturn($embed);

        $mediaclass = new Mediaclass($factory);

        $this->assertSame('https://cdn.example.com/vimeo-poster.jpg', $mediaclass->thumbnail($url));
        $this->assertSame('https://cdn.example.com/vimeo-poster.jpg', $mediaclass->thumbnail($url));
    }

    public function test_global_thumbnail_helper_uses_the_mediaclass_service(): void
    {
        $media = new Media([
            'mime' => 'video/url',
            'storable' => [
                'url' => 'https://vimeo.com/123456789',
                'thumbnail_url' => 'https://cdn.example.com/helper-poster.jpg',
            ],
        ]);

        $this->assertSame(
            'https://cdn.example.com/helper-poster.jpg',
            mediaclass_thumbnail($media),
        );
    }
}
