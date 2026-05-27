<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Unit;

use MetaFramework\Mediaclass\Tests\TestCase;

class UploaderAssetTest extends TestCase
{
    public function test_uploader_configures_lightgallery_thumbnails_from_preview_urls(): void
    {
        $uploaderScript = file_get_contents(__DIR__ . '/../../public/vendor/mfw-mediaclass/uploader.js');

        $this->assertStringContainsString("exThumbImage: 'data-thumb'", $uploaderScript);
        $this->assertStringContainsString('data-thumb="${preview}"', $uploaderScript);
        $this->assertStringContainsString("attr('data-thumb', thumbUrl)", $uploaderScript);
    }

    public function test_uploader_removes_nested_links_from_reloaded_preview_actions(): void
    {
        $uploaderScript = file_get_contents(__DIR__ . '/../../public/vendor/mfw-mediaclass/uploader.js');

        $this->assertStringContainsString('actionsWithoutLinks(actions)', $uploaderScript);
        $this->assertStringContainsString('$(this).replaceWith($(this).contents());', $uploaderScript);
    }
}
