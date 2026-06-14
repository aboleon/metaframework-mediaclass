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

    public function test_uploader_prints_success_response_messages(): void
    {
        $uploaderScript = file_get_contents(__DIR__ . '/../../public/vendor/mfw-mediaclass/uploader.js');

        $this->assertStringContainsString('printResponseMessages(uploadable, data)', $uploaderScript);
        $this->assertStringContainsString("instantiator.data('enforce-dimensions')", $uploaderScript);
        $this->assertStringContainsString("'dimension_recommendations'", $uploaderScript);
    }

    public function test_video_media_type_selection_opens_url_form(): void
    {
        $uploaderScript = file_get_contents(__DIR__ . '/../../public/vendor/mfw-mediaclass/uploader.js');

        $this->assertStringContainsString("const mediaTypes = uploadable.data('media-types');", $uploaderScript);
        $this->assertStringContainsString('return configuredTypes[0];', $uploaderScript);
        $this->assertStringContainsString("if (MediaclassUploader.selectedMediaType(uploadable) === 'video')", $uploaderScript);
        $this->assertStringContainsString('MediaclassUploader.toggleVideoUrlForm(uploadContainer);', $uploaderScript);
    }

    public function test_video_forms_include_editable_embed_dimensions(): void
    {
        $uploaderScript = file_get_contents(__DIR__ . '/../../public/vendor/mfw-mediaclass/uploader.js');
        $storedView = file_get_contents(__DIR__ . '/../../resources/views/components/stored.blade.php');

        $this->assertStringContainsString("videoDimensionsFields(uploadable, namePrefix = '', storable = {})", $uploaderScript);
        $this->assertStringContainsString('value="full"', $storedView);
        $this->assertStringContainsString('mediaclass-video-width-mode', $storedView);
        $this->assertStringContainsString('[embed_height]', $storedView);
    }

    public function test_video_previews_use_lightgallery_posters_and_autoplay(): void
    {
        $uploaderScript = file_get_contents(__DIR__ . '/../../public/vendor/mfw-mediaclass/uploader.js');
        $storedView = file_get_contents(__DIR__ . '/../../resources/views/components/stored.blade.php');

        $this->assertStringContainsString('data-poster="${preview}"', $uploaderScript);
        $this->assertStringContainsString('data-src="${link}"', $uploaderScript);
        $this->assertStringContainsString('plugins: [lgZoom, lgThumbnail, lgVideo]', $uploaderScript);
        $this->assertStringContainsString('autoplayFirstVideo: true', $uploaderScript);
        $this->assertStringContainsString('autoplayVideoOnSlide: true', $uploaderScript);
        $this->assertStringContainsString('data-poster="{{ $preview }}"', $storedView);
        $this->assertStringContainsString('plugins/video/lg-video.min.js', $storedView);
    }

    public function test_lightgallery_is_loaded_from_cdn_instead_of_a_published_distribution(): void
    {
        $storedView = file_get_contents(__DIR__ . '/../../resources/views/components/stored.blade.php');

        $this->assertStringContainsString(
            'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/css/lightgallery-bundle.min.css',
            $storedView,
        );
        $this->assertStringContainsString(
            'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/lightgallery.min.js',
            $storedView,
        );
        $this->assertDirectoryDoesNotExist(
            __DIR__ . '/../../public/vendor/mfw-mediaclass/lightgallery.js-master',
        );
    }
}
