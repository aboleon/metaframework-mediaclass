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

    public function test_uploadable_component_mounts_svelte_uploader(): void
    {
        $uploadableView = file_get_contents(__DIR__ . '/../../resources/views/components/uploadable.blade.php');

        $this->assertStringContainsString('mediaclass-svelte-uploader', $uploadableView);
        $this->assertStringContainsString('data-media-types', $uploadableView);
        $this->assertStringContainsString('data-ajax="{{ route(\'mediaclass.ajax\') }}"', $uploadableView);
        $this->assertStringNotContainsString('<x-mediaclass::template', $uploadableView);
        $this->assertStringContainsString('mediaclass-upload-container d-none', $uploadableView);
    }

    public function test_svelte_uploader_assets_are_loaded_without_blueimp_runtime(): void
    {
        $scriptsView = file_get_contents(__DIR__ . '/../../resources/views/scripts/js.blade.php');
        $stylesView = file_get_contents(__DIR__ . '/../../resources/views/scripts/css.blade.php');

        $this->assertStringContainsString('sortablejs@1.15.7/Sortable.min.js', $scriptsView);
        $this->assertStringContainsString("asset('vendor/mfw-mediaclass/uploader.js')", $scriptsView);
        $this->assertStringContainsString("asset('vendor/mfw-mediaclass/mediaclass-uploader.js')", $scriptsView);
        $this->assertStringContainsString("filemtime(public_path('vendor/mfw-mediaclass/uploader.js'))", $scriptsView);
        $this->assertStringContainsString("filemtime(public_path('vendor/mfw-mediaclass/mediaclass-uploader.js'))", $scriptsView);
        $this->assertStringNotContainsString('jQuery-File-Upload/js/jquery.fileupload.js', $scriptsView);
        $this->assertStringNotContainsString('jQuery-File-Upload/css/jquery.fileupload.css', $stylesView);
        $this->assertStringNotContainsString('mediaclass-uploader.css', $stylesView);
    }

    public function test_svelte_source_uploads_files_and_video_urls(): void
    {
        $svelteSource = file_get_contents(__DIR__ . '/../../resources/svelte/Uploadable.svelte');

        $this->assertStringContainsString("import { onDestroy, onMount } from 'svelte';", $svelteSource);
        $this->assertStringContainsString('let { host }: { host: HTMLElement } = $props();', $svelteSource);
        $this->assertStringContainsString('onMount(() => {', $svelteSource);
        $this->assertStringContainsString("uploadable = host.closest('.mediaclass-uploadable')", $svelteSource);
        $this->assertStringContainsString('<div class="mediaclass-svelte-panel">', $svelteSource);
        $this->assertStringNotContainsString('{@attach initHost}', $svelteSource);
        $this->assertStringNotContainsString('<style>', $svelteSource);
        $this->assertStringContainsString('new XMLHttpRequest', $svelteSource);
        $this->assertStringContainsString("formData.set('action', 'upload')", $svelteSource);
        $this->assertStringContainsString("formData.append('files[]', item.file)", $svelteSource);
        $this->assertStringContainsString("formData.set('action', 'uploadUrl')", $svelteSource);
        $this->assertStringContainsString("embedWidth: '560'", $svelteSource);
        $this->assertStringContainsString("embedHeight: '315'", $svelteSource);
        $this->assertStringContainsString('<option value="full">', $svelteSource);
    }

    public function test_compiled_svelte_uploader_is_shipped(): void
    {
        $compiledScript = file_get_contents(__DIR__ . '/../../public/vendor/mfw-mediaclass/mediaclass-uploader.js');
        $stylesheet = file_get_contents(__DIR__ . '/../../public/vendor/mfw-mediaclass/css/styles.css');

        $this->assertFileExists(__DIR__ . '/../../public/vendor/mfw-mediaclass/mediaclass-uploader.js');
        $this->assertStringContainsString('MediaclassSvelteUploader', $compiledScript);
        $this->assertStringContainsString('window.MediaclassSvelteUploader', $compiledScript);
        $this->assertStringContainsString('mediaclass-svelte-toolbar', $compiledScript);
        $this->assertStringContainsString('XMLHttpRequest', $compiledScript);
        $this->assertStringNotContainsString('document.createElement("style")', $compiledScript);
        $this->assertStringContainsString('.mediaclass-svelte-panel', $stylesheet);
        $this->assertStringContainsString('.mediaclass-svelte-toolbar', $stylesheet);
    }

    public function test_legacy_uploader_bridge_is_exposed_for_svelte(): void
    {
        $uploaderScript = file_get_contents(__DIR__ . '/../../public/vendor/mfw-mediaclass/uploader.js');

        $this->assertStringContainsString('window.MediaclassUploader = MediaclassUploader;', $uploaderScript);
        $this->assertStringContainsString('appendUploadedMedia(uploadable, data, hideDescription)', $uploaderScript);
        $this->assertStringContainsString('printResponseMessages(uploadable, data)', $uploaderScript);
    }

    public function test_delete_uses_the_owning_uploaders_ajax_url_and_full_media_identity(): void
    {
        $uploaderScript = file_get_contents(__DIR__ . '/../../public/vendor/mfw-mediaclass/uploader.js');

        $this->assertStringContainsString("deleteData.uploadable.attr('data-ajax')", $uploaderScript);
        $this->assertStringContainsString("model_id: uploadable.attr('data-model-id')", $uploaderScript);
        $this->assertStringContainsString("group: uploadable.attr('data-group')", $uploaderScript);
        $this->assertStringContainsString("String(response.deleted_id ?? '')", $uploaderScript);
        $this->assertStringNotContainsString(
            'mfwAjax(deleteData.formData, MediaclassUploader.template())',
            $uploaderScript,
        );
        $this->assertStringNotContainsString('ajaxSuccess.mediaclassDelete', $uploaderScript);
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

    public function test_uploader_supports_persistent_left_to_right_sorting(): void
    {
        $uploaderScript = file_get_contents(__DIR__ . '/../../public/vendor/mfw-mediaclass/uploader.js');
        $storedView = file_get_contents(__DIR__ . '/../../resources/views/components/stored.blade.php');
        $scriptsView = file_get_contents(__DIR__ . '/../../resources/views/scripts/js.blade.php');

        $this->assertStringContainsString('sortablejs@1.15.7/Sortable.min.js', $scriptsView);
        $this->assertStringContainsString("action: 'reorder'", $uploaderScript);
        $this->assertStringContainsString("handle: '.mediaclass-sort-handle'", $uploaderScript);
        $this->assertStringContainsString('media_ids: mediaIds', $uploaderScript);
        $this->assertStringContainsString('mediaclass:reordered', $uploaderScript);
        $this->assertStringContainsString('data-sort-order="{{ $media->sort_order }}"', $storedView);
        $this->assertStringContainsString('bi-grip-vertical', $storedView);
    }
}
