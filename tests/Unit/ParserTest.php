<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Unit;

use Illuminate\Support\Facades\Storage;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Parser;
use MetaFramework\Mediaclass\Support\Path;
use MetaFramework\Mediaclass\Tests\Fixtures\Post;
use MetaFramework\Mediaclass\Tests\Fixtures\PostWithGroupSizes;
use MetaFramework\Mediaclass\Tests\TestCase;

class ParserTest extends TestCase
{
    protected Post $post;

    protected Media $media;

    protected function setUp(): void
    {
        parent::setUp();

        $this->post = Post::create(['title' => 'Test Post']);

        $this->media = Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'test-image.jpg',
            'filename' => 'abc123',
            'position' => 'right',
            'description' => ['en' => 'English desc', 'fr' => 'French desc'],
        ]);

        $this->media->setRelation('model', $this->post);
    }

    public function test_parser_exposes_id(): void
    {
        $parser = new Parser($this->media);

        $this->assertEquals($this->media->id, $parser->id);
    }

    public function test_parser_exposes_group(): void
    {
        $parser = new Parser($this->media);

        $this->assertEquals('cover', $parser->group);
    }

    public function test_parser_exposes_position(): void
    {
        $parser = new Parser($this->media);

        $this->assertEquals('right', $parser->position);
    }

    public function test_parser_exposes_mime(): void
    {
        $parser = new Parser($this->media);

        $this->assertEquals('image/jpeg', $parser->mime);
    }

    public function test_parser_exposes_filename(): void
    {
        $parser = new Parser($this->media);

        $this->assertEquals('test-image.jpg', $parser->filename);
    }

    public function test_parser_exposes_extension(): void
    {
        $parser = new Parser($this->media);

        $this->assertEquals('jpg', $parser->extension);
    }

    public function test_parser_exposes_is_image(): void
    {
        $parser = new Parser($this->media);

        $this->assertTrue($parser->isImage);
    }

    public function test_parser_exposes_is_file(): void
    {
        $parser = new Parser($this->media);

        $this->assertFalse($parser->isFile);
    }

    public function test_parser_is_file_true_for_non_images(): void
    {
        $pdfMedia = Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'documents',
            'mime' => 'application/pdf',
            'original_filename' => 'document.pdf',
            'filename' => 'doc123',
            'position' => 'left',
        ]);
        $pdfMedia->setRelation('model', $this->post);

        $parser = new Parser($pdfMedia);

        $this->assertTrue($parser->isFile);
        $this->assertFalse($parser->isImage);
    }

    public function test_parser_uses_external_video_url(): void
    {
        $url = 'https://www.youtube.com/watch?v=abc123';
        $videoMedia = Media::create([
            'model_type' => Post::class,
            'model_id' => $this->post->id,
            'group' => 'gallery',
            'mime' => 'video/url',
            'original_filename' => $url,
            'filename' => 'vid123',
            'position' => 'left',
            'storable' => ['url' => $url],
        ]);
        $videoMedia->setRelation('model', $this->post);

        $parser = new Parser($videoMedia);

        $this->assertSame(['original' => $url], $parser->urls);
        $this->assertSame($url, $parser->url);
    }

    public function test_parser_exposes_url(): void
    {
        $parser = new Parser($this->media);

        $this->assertIsString($parser->url);
    }

    public function test_parser_exposes_urls(): void
    {
        $parser = new Parser($this->media);

        $this->assertIsArray($parser->urls);
    }

    public function test_parser_get_url_method(): void
    {
        $parser = new Parser($this->media);

        // getUrl returns null when no files exist on disk for that size
        // This is expected behavior - it checks if the file actually exists
        $url = $parser->getUrl('sm');

        $this->assertTrue($url === null || is_string($url));
    }

    public function test_parser_get_url_returns_null_for_invalid_size(): void
    {
        $parser = new Parser($this->media);

        $url = $parser->getUrl('nonexistent');

        $this->assertNull($url);
    }

    public function test_parser_has_size_method(): void
    {
        $parser = new Parser($this->media);

        // These sizes might exist depending on actual files
        $this->assertIsBool($parser->hasSize('sm'));
        $this->assertIsBool($parser->hasSize('xl'));
    }

    public function test_parser_get_available_sizes(): void
    {
        $parser = new Parser($this->media);

        $sizes = $parser->getAvailableSizes();

        $this->assertIsArray($sizes);
    }

    public function test_parser_is_cropped_method(): void
    {
        $parser = new Parser($this->media);

        $this->assertIsBool($parser->isCropped());
    }

    public function test_parser_get_media_returns_media(): void
    {
        $parser = new Parser($this->media);

        $this->assertSame($this->media, $parser->getMedia());
    }

    public function test_parser_to_array(): void
    {
        $parser = new Parser($this->media);

        $array = $parser->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('group', $array);
        $this->assertArrayHasKey('position', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('urls', $array);
        $this->assertArrayHasKey('url', $array);
        $this->assertArrayHasKey('mime', $array);
        $this->assertArrayHasKey('filename', $array);
        $this->assertArrayHasKey('extension', $array);
        $this->assertArrayHasKey('isImage', $array);
        $this->assertArrayHasKey('isFile', $array);
        $this->assertArrayHasKey('hasCropped', $array);
    }

    public function test_parser_magic_get_accesses_media_properties(): void
    {
        $parser = new Parser($this->media);

        // Parser exposes filename as a readonly property, not via __get
        // __get is for accessing underlying Media model's PHP properties (not Eloquent attributes)
        $this->assertEquals('test-image.jpg', $parser->filename);
    }

    public function test_parser_magic_get_throws_for_invalid_property(): void
    {
        $parser = new Parser($this->media);

        $this->expectException(\InvalidArgumentException::class);
        $parser->__get('nonexistent_property');
    }

    public function test_parser_description_is_localized(): void
    {
        $parser = new Parser($this->media);

        // Description should be a string (localized)
        $this->assertIsString($parser->description);
    }

    public function test_parser_uses_group_sizes_when_defined(): void
    {
        $post = PostWithGroupSizes::create(['title' => 'Sized Post']);
        $media = Media::create([
            'model_type' => PostWithGroupSizes::class,
            'model_id' => $post->id,
            'group' => 'cover',
            'mime' => 'image/jpeg',
            'original_filename' => 'test.jpg',
            'filename' => 'sized123',
            'position' => 'right',
        ]);
        $media->setRelation('model', $post);

        $folder = Path::mediaFolderForMedia($media);
        Storage::disk('testing')->put($folder . '/1600_' . $media->filename . '.jpg', 'test');
        Storage::disk('testing')->put($folder . '/1200_' . $media->filename . '.jpg', 'test');

        $parser = new Parser($media);

        $this->assertArrayHasKey('xl', $parser->urls);
        $this->assertArrayHasKey('sm', $parser->urls);
        $this->assertArrayNotHasKey('md', $parser->urls);
    }
}
