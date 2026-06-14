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

    <div class="mediaclass-svelte-uploader" data-icon="{{ $icon }}" data-label="{{ strip_tags($displayLabel) }}"
        data-dimensions-inline="{{ $dimensionsInline }}">
    </div>
    <div class="mediaclass-upload-container d-none"></div>
    <div class="uploaded">
        <x-mediaclass::stored :cropable="$cropable" :positions="$positions" :model="$model" :nomedia="$nomedia" :group="$group"
            :subgroup="$settings['subgroup'] ?? null" :description="$description" :ghost="$ghost" :storables="$storables" :grid="$grid" />
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
    <x-mediaclass::crop-template />
@endonce

@once
    <x-mediaclass::crop-modal />
    <x-mediaclass::confirm-delete-modal />
@endonce

@pushonce('js', 'mediaclass-subgroups')
    <script>
        $(function() {
            function parseMediaclassSubgroupJson(value) {
                try {
                    return JSON.parse(String(value || '{}'));
                } catch (error) {
                    return {};
                }
            }

            function mediaclassSubgroupConfig(uploadable) {
                const options = parseMediaclassSubgroupJson(uploadable.attr('data-subgroup-options'));

                if (Object.keys(options).length < 1) {
                    return null;
                }

                return {
                    options: options,
                    values: parseMediaclassSubgroupJson(uploadable.attr('data-subgroup-values')),
                    label: String(uploadable.attr('data-subgroup-label') || 'Display group'),
                    emptyLabel: String(uploadable.attr('data-subgroup-empty-label') || 'No subgroup')
                };
            }

            function ensureMediaclassSubgroupSelect(uploadedImage, uploadable, config) {
                const mediaId = String(uploadedImage.data('id') || '');

                if (!mediaId || String(uploadedImage.data('bridge') || '0') === '1') {
                    return;
                }

                if (uploadedImage.find('[data-mediaclass-subgroup-select]').length) {
                    return;
                }

                if (!uploadedImage.find('.preview.image').length) {
                    return;
                }

                const selectedValue = String(config.values[mediaId] || ''),
                    control = $('<div>', {
                        class: 'col-12 mediaclass-subgroup ps-2 mb-2'
                    }),
                    label = $('<label>', {
                        class: 'form-label fw-semibold mb-1',
                        text: config.label
                    }),
                    select = $('<select>', {
                        class: 'form-control form-control-sm',
                        'data-mediaclass-subgroup-select': '1',
                        'data-saved-value': selectedValue
                    });

                select.append($('<option>', {
                    value: '',
                    text: config.emptyLabel
                }));

                Object.keys(config.options).forEach(function(value) {
                    select.append($('<option>', {
                        value: value,
                        text: config.options[value]
                    }));
                });

                select.val(selectedValue);
                control.append(label, select);

                const params = uploadedImage.find('.row.params').first();

                if (params.length) {
                    params.prepend(control);

                    return;
                }

                uploadedImage.find('.impFileName').first().append(control);
            }

            function attachMediaclassSubgroupSelects(uploadable) {
                const config = mediaclassSubgroupConfig(uploadable);

                if (!config) {
                    return;
                }

                uploadable.find('.uploaded .mediaclass.uploaded-image').each(function() {
                    ensureMediaclassSubgroupSelect($(this), uploadable, config);
                });
            }

            $('.mediaclass-uploadable').each(function() {
                const uploadable = $(this),
                    uploaded = uploadable.find('.uploaded').first();

                attachMediaclassSubgroupSelects(uploadable);

                if (window.MutationObserver && uploaded.length) {
                    new MutationObserver(function() {
                        attachMediaclassSubgroupSelects(uploadable);
                    }).observe(uploaded[0], {
                        childList: true,
                        subtree: true
                    });
                }
            });

            $(document).on('change', '[data-mediaclass-subgroup-select]', function() {
                const select = $(this),
                    uploadedImage = select.closest('.mediaclass.uploaded-image'),
                    uploadable = select.closest('.mediaclass-uploadable');

                const payload = $.param({
                    action: 'saveSubgroup',
                    model: uploadable.data('model') || '',
                    model_id: uploadable.data('model-id') || '',
                    group: uploadable.data('group') || '',
                    ghost: uploadable.data('ghost') || '0',
                    media_id: uploadedImage.data('id'),
                    subgroup: select.val() || ''
                });

                mfwAjax(payload, uploadable, {
                    errorHandler: function() {
                        select.val(select.attr('data-saved-value') || '');

                        return true;
                    },
                    successHandler: function(result) {
                        const values = parseMediaclassSubgroupJson(uploadable.attr(
                                'data-subgroup-values')),
                            mediaId = String(result.media_id),
                            subgroup = result.subgroup ? String(result.subgroup) : '';

                        if (subgroup) {
                            values[mediaId] = subgroup;
                        } else {
                            delete values[mediaId];
                        }

                        uploadable.attr('data-subgroup-values', JSON.stringify(values));
                        select.val(subgroup).attr('data-saved-value', subgroup);
                        $(document).trigger('mediaclass:subgroup-saved', [result, uploadable,
                            select
                        ]);

                        return true;
                    }
                });
            });
        });
    </script>
@endpushonce
