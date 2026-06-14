<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Unit;

use InvalidArgumentException;
use MetaFramework\Mediaclass\Support\Config;
use MetaFramework\Mediaclass\Tests\TestCase;

class ConfigTest extends TestCase
{
    public function test_configured_mediaclass_disk_is_used_for_urls(): void
    {
        $this->assertSame(
            'http://localhost/storage/example/image.jpg',
            Config::getDisk()->url('example/image.jpg'),
        );
    }

    public function test_invalid_configured_disk_does_not_silently_fall_back_to_public(): void
    {
        config()->set('mfw-mediaclass.disk', 'missing-mediaclass-disk');

        $this->expectException(InvalidArgumentException::class);

        Config::getDisk();
    }
}
