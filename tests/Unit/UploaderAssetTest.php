<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Unit;

use MetaFramework\Mediaclass\Tests\TestCase;

class UploaderAssetTest extends TestCase
{
    public function test_uploader_configures_lightgallery_thumbnails_from_preview_urls(): void
    {
        $managerSource = file_get_contents(__DIR__ . '/../../resources/svelte/media-manager.js');

        $this->assertStringContainsString("exThumbImage: 'data-thumb'", $managerSource);
        $this->assertStringContainsString('data-thumb="${preview}"', $managerSource);
        $this->assertStringContainsString("attr('data-thumb', thumbUrl)", $managerSource);
    }

    public function test_uploader_removes_nested_links_from_reloaded_preview_actions(): void
    {
        $managerSource = file_get_contents(__DIR__ . '/../../resources/svelte/media-manager.js');

        $this->assertStringContainsString('actionsWithoutLinks(actions)', $managerSource);
        $this->assertStringContainsString('$(this).replaceWith($(this).contents());', $managerSource);
    }

    public function test_uploader_prints_success_response_messages(): void
    {
        $managerSource = file_get_contents(__DIR__ . '/../../resources/svelte/media-manager.js');
        $svelteSource = file_get_contents(__DIR__ . '/../../resources/svelte/Uploadable.svelte');

        $this->assertStringContainsString('printResponseMessages(uploadable, data)', $managerSource);
        $this->assertStringContainsString("text('dimension_requirements'", $svelteSource);
        $this->assertStringContainsString("text('dimension_recommendations'", $svelteSource);
    }

    public function test_uploadable_component_mounts_svelte_uploader(): void
    {
        $uploadableView = file_get_contents(__DIR__ . '/../../resources/views/components/uploadable.blade.php');

        $this->assertStringContainsString('mediaclass-svelte-uploader', $uploadableView);
        $this->assertStringContainsString('data-media-types', $uploadableView);
        $this->assertStringContainsString('data-ajax="{{ route(\'mediaclass.ajax\') }}"', $uploadableView);
        $this->assertStringNotContainsString('<x-mediaclass::template', $uploadableView);
        $this->assertStringNotContainsString('mediaclass-upload-container', $uploadableView);
        $this->assertStringNotContainsString('<style>', $uploadableView);
        $this->assertStringNotContainsString('<script>', $uploadableView);
    }

    public function test_svelte_uploader_assets_are_loaded_without_blueimp_runtime(): void
    {
        $scriptsView = file_get_contents(__DIR__ . '/../../resources/views/scripts/js.blade.php');
        $stylesView = file_get_contents(__DIR__ . '/../../resources/views/scripts/css.blade.php');

        $this->assertStringContainsString('sortablejs@1.15.7/Sortable.min.js', $scriptsView);
        $this->assertStringContainsString("asset('vendor/mfw-mediaclass/mediaclass-uploader.js')", $scriptsView);
        $this->assertStringContainsString("filemtime(public_path('vendor/mfw-mediaclass/mediaclass-uploader.js'))", $scriptsView);
        $this->assertStringNotContainsString('uploader.js', str_replace('mediaclass-uploader.js', '', $scriptsView));
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
        $this->assertStringContainsString("embedHeightMode: 'auto'", $svelteSource);
        $this->assertStringContainsString('<option value="auto">', $svelteSource);
        $this->assertStringContainsString("disabled={video.embedHeightMode === 'auto'}", $svelteSource);
        $this->assertStringContainsString('<option value="full">', $svelteSource);
    }

    public function test_selecting_video_opens_the_video_url_panel(): void
    {
        $svelteSource = file_get_contents(__DIR__ . '/../../resources/svelte/Uploadable.svelte');

        $this->assertMatchesRegularExpression(
            "/function selectType\\(type: string\\): void \\{.*syncStoredMediaState\\(\\);.*setVideoPanelOpen\\(type === 'video'\\);.*\\}/s",
            $svelteSource,
        );
        $this->assertStringContainsString("setVideoPanelOpen(type === 'video');", $svelteSource);
        $this->assertStringContainsString('{#if showVideoPanel}', $svelteSource);
        $this->assertStringContainsString('type="url"', $svelteSource);
    }

    public function test_video_panel_hides_the_empty_media_notice_while_open(): void
    {
        $svelteSource = file_get_contents(__DIR__ . '/../../resources/svelte/Uploadable.svelte');

        $this->assertMatchesRegularExpression(
            "/function setVideoPanelOpen\\(open: boolean\\): void \\{.*showVideoPanel = open;.*querySelectorAll<HTMLElement>\\('\\[data-mediaclass-empty-state\\]'\\).*emptyState.hidden = open;.*\\}/s",
            $svelteSource,
        );
        $this->assertStringContainsString('onclick={() => setVideoPanelOpen(false)}', $svelteSource);
        $this->assertMatchesRegularExpression(
            '/new MutationObserver\\(\\(\\) => \\{.*syncStoredMediaState\\(\\);.*setVideoPanelOpen\\(showVideoPanel\\);.*\\}\\)/s',
            $svelteSource,
        );
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
        $this->assertStringContainsString('MediaclassManager', $compiledScript);
        $this->assertStringNotContainsString('document.createElement("style")', $compiledScript);
        $this->assertStringContainsString('.mediaclass-svelte-panel', $stylesheet);
        $this->assertStringContainsString('.mediaclass-svelte-toolbar', $stylesheet);
    }

    public function test_media_manager_is_bundled_with_the_svelte_uploader(): void
    {
        $managerSource = file_get_contents(__DIR__ . '/../../resources/svelte/media-manager.js');
        $entrySource = file_get_contents(__DIR__ . '/../../resources/svelte/mediaclass-uploader.ts');

        $this->assertStringContainsString('window.MediaclassManager = MediaclassManager;', $managerSource);
        $this->assertStringContainsString('appendUploadedMedia(uploadable, data, hideDescription)', $managerSource);
        $this->assertStringContainsString('printResponseMessages(uploadable, data)', $managerSource);
        $this->assertStringContainsString("import { initMediaManager } from './media-manager.js';", $entrySource);
    }

    public function test_delete_uses_the_owning_uploaders_ajax_url_and_full_media_identity(): void
    {
        $managerSource = file_get_contents(__DIR__ . '/../../resources/svelte/media-manager.js');

        $this->assertStringContainsString("deleteData.uploadable.attr('data-ajax')", $managerSource);
        $this->assertStringContainsString("model_id: uploadable.attr('data-model-id')", $managerSource);
        $this->assertStringContainsString("group: uploadable.attr('data-group')", $managerSource);
        $this->assertStringContainsString("String(response.deleted_id ?? '')", $managerSource);
        $this->assertStringNotContainsString('ajaxSuccess.mediaclassDelete', $managerSource);
    }

    public function test_empty_media_notice_tracks_uploads_and_deletions(): void
    {
        $managerSource = file_get_contents(__DIR__ . '/../../resources/svelte/media-manager.js');
        $storedView = file_get_contents(__DIR__ . '/../../resources/views/components/stored.blade.php');

        $this->assertStringContainsString('syncEmptyState(uploadable)', $managerSource);
        $this->assertStringContainsString('MediaclassManager.syncEmptyState(deleteData.uploadable)', $managerSource);
        $this->assertStringContainsString('this.syncEmptyState(uploadable)', $managerSource);
        $this->assertStringContainsString("alertsContainer.find('[data-mediaclass-empty-state]').remove()", $managerSource);
        $this->assertStringContainsString('data-mediaclass-empty-state', $storedView);
        $this->assertStringNotContainsString('alertsContainer.html(`<div class="alert alert-info">', $managerSource);
    }

    public function test_video_forms_include_editable_embed_dimensions(): void
    {
        $managerSource = file_get_contents(__DIR__ . '/../../resources/svelte/media-manager.js');
        $storedView = file_get_contents(__DIR__ . '/../../resources/views/components/stored.blade.php');

        $this->assertStringContainsString("videoDimensionsFields(uploadable, namePrefix = '', storable = {})", $managerSource);
        $this->assertStringContainsString('value="full"', $storedView);
        $this->assertStringContainsString('mediaclass-video-width-mode', $storedView);
        $this->assertStringContainsString('mediaclass-video-height-mode', $storedView);
        $this->assertStringContainsString("heightInput.prop('disabled', select.val() === 'auto')", $managerSource);
        $this->assertStringContainsString('[embed_height]', $storedView);
    }

    public function test_video_previews_use_lightgallery_posters_and_autoplay(): void
    {
        $managerSource = file_get_contents(__DIR__ . '/../../resources/svelte/media-manager.js');
        $storedView = file_get_contents(__DIR__ . '/../../resources/views/components/stored.blade.php');

        $this->assertStringContainsString('data-poster="${preview}"', $managerSource);
        $this->assertStringContainsString('data-src="${this.escapeHtml(link)}"', $managerSource);
        $this->assertStringContainsString('plugins: [lgZoom, lgThumbnail, lgVideo]', $managerSource);
        $this->assertStringContainsString('autoplayFirstVideo: true', $managerSource);
        $this->assertStringContainsString('autoplayVideoOnSlide: true', $managerSource);
        $this->assertStringContainsString('data-poster="{{ $preview }}"', $storedView);
        $this->assertStringContainsString('plugins/video/lg-video.min.js', $storedView);
    }

    public function test_html5_video_previews_use_explicit_lightgallery_video_data(): void
    {
        $managerSource = file_get_contents(__DIR__ . '/../../resources/svelte/media-manager.js');
        $storedView = file_get_contents(__DIR__ . '/../../resources/views/components/stored.blade.php');

        $this->assertStringContainsString("data-video='@json(\$html5_video", $storedView);
        $this->assertStringContainsString('html5VideoMimeType(url)', $managerSource);
        $this->assertStringContainsString("attributes: { preload: 'metadata', playsinline: true, controls: true }", $managerSource);
        $this->assertStringContainsString("? `data-video='", $managerSource);
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
        $managerSource = file_get_contents(__DIR__ . '/../../resources/svelte/media-manager.js');
        $storedView = file_get_contents(__DIR__ . '/../../resources/views/components/stored.blade.php');
        $scriptsView = file_get_contents(__DIR__ . '/../../resources/views/scripts/js.blade.php');

        $this->assertStringContainsString('sortablejs@1.15.7/Sortable.min.js', $scriptsView);
        $this->assertStringContainsString("action: 'reorder'", $managerSource);
        $this->assertStringContainsString("handle: '.mediaclass-sort-handle'", $managerSource);
        $this->assertStringContainsString('media_ids: mediaIds', $managerSource);
        $this->assertStringContainsString('mediaclass:reordered', $managerSource);
        $this->assertStringContainsString('data-sort-order="{{ $media->sort_order }}"', $storedView);
        $this->assertStringContainsString('bi-grip-vertical', $storedView);
    }

    public function test_v1_does_not_ship_blueimp_or_legacy_uploader_components(): void
    {
        $this->assertDirectoryDoesNotExist(
            __DIR__ . '/../../public/vendor/mfw-mediaclass/jQuery-File-Upload',
        );
        $this->assertFileDoesNotExist(__DIR__ . '/../../public/vendor/mfw-mediaclass/uploader.js');
        $this->assertFileDoesNotExist(__DIR__ . '/../../src/Components/Template.php');
        $this->assertFileDoesNotExist(__DIR__ . '/../../resources/views/components/template.blade.php');
        $this->assertFileDoesNotExist(__DIR__ . '/../../resources/views/fileupload_scripts.blade.php');
        $this->assertFileExists(__DIR__ . '/../../resources/views/assets.blade.php');
    }
}
