@php
    $positions = array_key_exists('positions', $settings) && $settings['positions'] === true;
    $i18n = [
        'limit_reached' => __('mfw-mediaclass.notices.limit_reached'),
        'dimension_requirements' => __('mfw-mediaclass.notices.dimension_requirements'),
        'dimension_recommendations' => __('mfw-mediaclass.notices.dimension_recommendations'),
        'uploaded_at' => __('mfw-mediaclass.uploaded_at'),
        'positions_label' => __('mfw-mediaclass.labels.positions'),
        'description_label' => __('mfw-mediaclass.labels.description'),
        'accept_file_types' => __('mfw-mediaclass.errors.acceptFileTypes'),
        'min_image_width' => __('mfw-mediaclass.errors.minImageWidth'),
        'min_image_height' => __('mfw-mediaclass.errors.minImageHeight'),
        'image_dimensions' => __('mfw-mediaclass.errors.imageDimensions'),
        'upload_error_title' => __('mfw-mediaclass.errors.upload_error_title'),
        'upload_error_generic' => __('mfw-mediaclass.errors.upload_error_generic'),
        'invalid_url' => __('mfw-mediaclass.errors.invalidUrl'),
        'video_url_label' => __('mfw-mediaclass.labels.video_url'),
        'video_url_placeholder' => __('mfw-mediaclass.labels.video_url_placeholder'),
        'video_width_label' => __('mfw-mediaclass.labels.video_width'),
        'video_height_label' => __('mfw-mediaclass.labels.video_height'),
        'video_height_auto' => __('mfw-mediaclass.labels.video_height_auto'),
        'video_width_pixels' => __('mfw-mediaclass.labels.video_width_pixels'),
        'video_width_full' => __('mfw-mediaclass.labels.video_width_full'),
        'add' => __('mfw-mediaclass.buttons.add'),
        'cancel' => __('mfw-mediaclass.buttons.cancel'),
        'save_media_details' => __('mfw-mediaclass.buttons.save_media_details'),
        'subgroup_label' => $subgroupLabel,
        'subgroup_empty_label' => $subgroupEmptyLabel,
        'sort_handle' => __('mfw-mediaclass.labels.sort_handle'),
        'reorder_failed' => __('mfw-mediaclass.errors.reorderFailed'),
    ];
@endphp
<div class="mediaclass-uploadable {{ $size }}" data-maxfilesize="{{ $maxfilesize }}"
    data-limit="{{ $limit }}" data-model="{{ get_class($model) }}" data-model-id="{{ $model->id ?? '' }}"
    data-positions="{{ $positions }}" data-group="{{ $group }}"
    data-subgroup="{{ $settings['subgroup'] ?? false }}" data-has-description="{{ $description }}"
    data-cropable="{{ $cropable }}" data-ghost="{{ $ghost ? '1' : '0' }}" data-grid="{{ $grid }}"
    data-enforce-dimensions="{{ $enforceDimensions ? '1' : '0' }}" data-i18n='@json($i18n, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS)'
    data-media-types='@json($mediaTypeOptions, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS)' data-media-locales='@json($mediaLocales, JSON_UNESCAPED_UNICODE)'
    data-ajax="{{ route('mediaclass.ajax') }}"
    @if ($subgroupOptions !== []) data-subgroup-options='@json($subgroupOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'
     data-subgroup-values='@json($subgroupValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'
     data-subgroup-label="{{ $subgroupLabel }}"
     data-subgroup-empty-label="{{ $subgroupEmptyLabel }}" @endif
    @if ($callback) data-callback="{{ $callback }}" @endif
    @if ($requiredWidth && $requiredHeight) data-required-width="{{ $requiredWidth }}"
     data-required-height="{{ $requiredHeight }}" @endif>


    @foreach ((array) $storables as $key => $value)
        <input type="hidden" name="mediaclass_storable[{{ $key }}]" value="{{ $value }}" />
    @endforeach

    <div class="mediaclass-svelte-uploader" data-icon="{{ $icon }}" data-label="{{ strip_tags($label) }}"
        data-dimensions-inline="{{ $dimensionsInline }}">
    </div>
    <div class="uploaded">
        <x-mediaclass::stored :cropable="$cropable" :positions="$positions" :model="$model" :nomedia="$nomedia" :group="$group"
            :subgroup="$settings['subgroup'] ?? null" :description="$description" :ghost="$ghost" :storables="$storables" :grid="$grid" />
    </div>
</div>

@once
    @if ($model instanceof \MetaFramework\Mediaclass\Contracts\MediaclassInterface && !isset($model->id) && !$ghost)
        <input type="hidden" name="mediaclass_temp_id" value="{{ Str::random(32) }}">
    @endif


    @include('mediaclass::assets')
    <x-mediaclass::crop-template />
@endonce

@once
    <x-mediaclass::crop-modal />
    <x-mediaclass::confirm-delete-modal />
@endonce
