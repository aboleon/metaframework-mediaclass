@php
    $positions = (array_key_exists('positions',$settings) && $settings['positions'] === true);
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
        @if($requiredWidth && $requiredHeight)
            <span class="subcontrol dimensions-badge me-3"
                  style="font-size: 16px; background: rgba(0,0,0,0.1); padding: 2px 8px; border-radius: 4px;">
            {{ $requiredWidth }}px × {{ $requiredHeight }}px
        </span>
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
    @if ($model instanceof \MetaFramework\Mediaclass\Interfaces\MediaclassInterface && !isset($model->id) && !$ghost)
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
