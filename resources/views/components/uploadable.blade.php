@php
    $positions = (array_key_exists('positions',$settings) && $settings['positions'] === true);
    $i18n = [
        'limit_reached' => __('mfw-mediaclass.notices.limit_reached'),
        'dimension_requirements' => __('mfw-mediaclass.notices.dimension_requirements'),
        'uploaded_at' => __('mfw-mediaclass.uploaded_at'),
        'positions_label' => __('mfw-mediaclass.labels.positions'),
        'description_label' => __('mfw-mediaclass.labels.description'),
        'accept_file_types' => __('mfw-mediaclass.errors.acceptFileTypes'),
        'min_image_width' => __('mfw-mediaclass.errors.minImageWidth'),
        'min_image_height' => __('mfw-mediaclass.errors.minImageHeight'),
        'image_dimensions' => __('mfw-mediaclass.errors.imageDimensions'),
        'upload_error_title' => __('mfw-mediaclass.errors.upload_error_title'),
        'upload_error_generic' => __('mfw-mediaclass.errors.upload_error_generic'),
    ];
@endphp
<div class="mediaclass-uploadable {{ $size }}"
     data-maxfilesize="{{ $maxfilesize }}"
     data-limit="{{ $limit }}"
     data-model="{{ get_class($model) }}"
     data-model-id="{{ $model->id ?? '' }}"
     data-positions="{{ $positions }}"
     data-group="{{ $group }}"
     data-subgroup="{{ $settings['subgroup'] ?? false }}"
     data-has-description="{{ $description }}"
     data-cropable="{{ $cropable }}"
     data-ghost="{{ $ghost ? '1' : '0' }}"
     data-i18n='@json($i18n, JSON_UNESCAPED_UNICODE)'
     @if($callback)
         data-callback="{{ $callback }}"
     @endif
     @if($requiredWidth && $requiredHeight)
         data-required-width="{{ $requiredWidth }}"
     data-required-height="{{ $requiredHeight }}"
        @endif
>


    @foreach((array)$storables as $key => $value)
        <input type="hidden" name="mediaclass_storable[{{ $key }}]" value="{{ $value }}"/>
    @endforeach

    <div class="controls d-flex justify-content-between align-items-center" style="background: #EFEFEF">
        <span class="subcontrol mediaclass-uploader">
            <i class="{{ $icon }}"></i> {!! $displayLabel !!}
        </span>
        @if($dimensionsInline)
            <span class="subcontrol dimensions-inline me-3">{{ $dimensionsInline }}</span>
        @endif
    </div>
    <div class="mediaclass-upload-container"></div>
    <div class="uploaded">
        <x-mediaclass::stored :cropable="$cropable"
                              :positions="$positions"
                              :model="$model"
                              :nomedia="$nomedia"
                              :group="$group"
                              :subgroup="$settings['subgroup'] ?? null "
                              :description="$description"
                              :ghost="$ghost"
                              :storables="$storables"/>
    </div>
</div>

@once
    @push('css')
        <style>
            .mediaclass-uploadable .controls {
                transition: all 0.2s ease;
            }

            .mediaclass-uploadable .controls:hover {
                background: #E5E5E5 !important;
            }

            .mediaclass-uploadable .dimensions-info {
                font-size: 0.85em;
                opacity: 0.8;
                font-weight: normal;
            }

            .mediaclass-uploadable .dimensions-badge {
                font-family: monospace;
                font-weight: 600;
            }

            .mediaclass-uploadable .dimensions-inline {
                font-family: monospace;
                font-size: 13px;
                opacity: 0.8;
            }

            /* Disabled state styling */
            .mediaclass-uploadable .mediaclass-uploader.disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            /* Add visual hint for dimension requirements */
            .mediaclass-uploadable[data-required-width] .mediaclass-upload-container {
                position: relative;
            }

            .mediaclass-uploadable[data-required-width] .fileupload-buttonbar::before {
                content: attr(data-dimensions-hint);
                display: block;
                text-align: center;
                font-size: 12px;
                color: #666;
                margin-bottom: 10px;
            }
        </style>
    @endpush
@endonce

@once
    @if ($model instanceof \MetaFramework\Mediaclass\Contracts\MediaclassInterface && !isset($model->id) && !$ghost)
        <input type="hidden" name="mediaclass_temp_id" value="{{ Str::random(32) }}">
    @endif


    @include('mediaclass::fileupload_scripts')
    <x-mediaclass::template/>
    <x-mediaclass::crop-template/>
@endonce

@once
    <x-mediaclass::crop-modal/>
    <x-mediaclass::confirm-delete-modal/>
@endonce
