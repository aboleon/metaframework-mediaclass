@if ($medias->isNotEmpty())
    {{-- LightGallery container --}}
    <div id="lightgallery-{{ $group }}-{{ $model->id }}"
        class="lightgallery-container mediaclass-stored-grid mediaclass-stored-grid--{{ $grid }}"
        data-grid="{{ $grid }}">
        @foreach ($medias as $media)
            @php
                $is_bridge = $media instanceof \MetaFramework\Mediaclass\Support\BridgeMedia;
                $media_dom_id = $is_bridge ? $media->domId() : $media->id;
                $media_key = $is_bridge ? $media->key() : $media->id;
                $is_image = $media->isImage();
                $is_video = $media->isVideo();
                $embed_width = $is_video && !$is_bridge ? $media->embedWidth() : null;
                $embed_height = $is_video && !$is_bridge ? $media->embedHeight() : null;
                $embed_width_mode = $embed_width === '100%' ? 'full' : 'pixels';
                $video_lightgallery_width = is_int($embed_width)
                    ? $embed_width
                    : \MetaFramework\Mediaclass\Models\Media::DEFAULT_EMBED_WIDTH;
                $cropableImg = $is_bridge || !$is_image ? null : new \MetaFramework\Mediaclass\Cropable($media);
                $cropableImg?->setCropableFromComponent($cropable);
                $preview = match (true) {
                    $is_image => $media->url($cropableImg?->isCropped() ? 'cropped' : 'sm'),
                    $is_video => $videoPreviews[(string) $media->id] ??
                        asset('vendor/mfw-mediaclass/images/files/mov.png'),
                    default => asset('vendor/mfw-mediaclass/images/files/' . $media->extension() . '.png'),
                };
                $fullSizeUrl = $is_image ? $media->url('xl') : null;
                $previewType = $is_image ? 'image' : ($is_video ? 'video' : 'file');
                $uploadedAt = $media->created_at
                    ? __('mfw-mediaclass.uploaded_at', [
                        'date' => $media->created_at->format('d/m/Y'),
                        'time' => $media->created_at->format('H:i'),
                    ])
                    : null;
            @endphp
            <div class="mediaclass unlinkable uploaded-image my-2" data-id="{{ $media->id }}"
                data-bridge="{{ $is_bridge ? '1' : '0' }}"
                @if (!$is_bridge) data-sort-order="{{ $media->sort_order }}" @endif
                id="mediaclass-{{ $media_dom_id }}">
                @if ($is_bridge)
                    <input type="hidden" name="mediaclass_bridge[{{ $group }}][{{ $media_key }}][id]"
                        value="{{ $media->id }}">
                @else
                    <span class="mediaclass-sort-handle" role="button" tabindex="0"
                        title="{{ __('mfw-mediaclass.labels.sort_handle') }}"
                        aria-label="{{ __('mfw-mediaclass.labels.sort_handle') }}">
                        <i class="bi bi-grip-vertical"></i>
                    </span>
                @endif
                <span class="unlink"><i class="bi bi-x-circle-fill"></i></span>
                <div class="row m-0">
                    <div class="col-xl-3 pe-xl-4 col-12 impImg position-relative preview {{ $previewType }}">
                        @if ($is_image)
                            <a href="{{ $fullSizeUrl }}" class="lightgallery-item d-block w-100 h-100"
                                data-thumb="{{ $preview }}"
                                data-sub-html="<h4>{{ $media->original_filename }}</h4><p>{{ $media->description[app()->getLocale()] ?? '' }}</p>"
                                style="background-image: url({{ $preview }});background-size: contain;background-repeat: no-repeat;background-position: center;">
                                <div class="actions">
                                    <i class="bi bi-zoom-in"></i>
                                </div>
                            </a>
                        @elseif ($is_video && !$is_bridge)
                            <a href="{{ $media->url() }}" data-src="{{ $media->url() }}"
                                data-poster="{{ $preview }}" data-thumb="{{ $preview }}"
                                data-download-url="false"
                                data-lg-size="{{ $video_lightgallery_width }}-{{ $embed_height }}"
                                data-sub-html="<h4>{{ $media->original_filename }}</h4><p>{{ $media->description[app()->getLocale()] ?? '' }}</p>"
                                class="lightgallery-item d-block w-100 h-100"
                                style="background-image: url({{ $preview }});background-size: cover;background-repeat: no-repeat;background-position: center;">
                                <div class="actions">
                                    <i class="bi bi-play-circle-fill"></i>
                                </div>
                            </a>
                        @else
                            <div class="w-100 h-100"
                                style="background-image: url({{ $preview }});background-size: contain;background-repeat: no-repeat;background-position: center;">
                                <div class="actions">
                                    <a target="_blank" href="{{ $media->url() }}" class="zoom">
                                        <i class="bi bi-zoom-in"></i>
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="col-xl-9 col-12 impFileName">
                        <div class="row infos">
                            <div class="col-12">
                                <p class="name">
                                    <span
                                        class="filename rounded-1 text-bg-secondary px-2 py-1">{{ $media->original_filename }}</span>
                                    @if ($uploadedAt)
                                        <span class="uploaded-at bg-light-subtle text-dark opacity-75">
                                            {{ $uploadedAt }}
                                        </span>
                                    @endif
                                </p>
                            </div>
                        </div>

                        @if ($is_image && !$is_bridge)
                            {!! $cropableImg->links() !!}
                        @endif

                        <div class="row params mt-3">
                            <div class="col-12 positions ps-2{{ $positions ? '' : ' d-none' }} text-center">
                                <b>{{ __('mfw-mediaclass.labels.positions') }}</b>
                                <div class="choices pt-2">
                                    @foreach ($getPositionning() as $p)
                                        <i class="bi bi-arrow-{{ $p }}-square-fill{{ $media->position == $p ? ' active' : '' }}"
                                            data-position="{{ $p }}"></i>
                                    @endforeach
                                    <input type="hidden"
                                        name="{{ $is_bridge ? 'mediaclass_bridge[' . $group . '][' . $media_key . '][position]' : 'mediaclass[' . $media->id . '][position]' }}"
                                        value="{{ $media->position }}">
                                </div>
                            </div>

                            @if ($is_video && !$is_bridge)
                                <div class="col-12 mediaclass-video-dimensions">
                                    <div class="row">
                                        <div class="col-md-6 col-12">
                                            <label
                                                class="form-label">{{ __('mfw-mediaclass.labels.video_width') }}</label>
                                            <div class="input-group">
                                                <select name="mediaclass[{{ $media->id }}][embed_width_mode]"
                                                    class="form-select mediaclass-video-width-mode">
                                                    <option value="pixels" @selected($embed_width_mode === 'pixels')>
                                                        {{ __('mfw-mediaclass.labels.video_width_pixels') }}
                                                    </option>
                                                    <option value="full" @selected($embed_width_mode === 'full')>
                                                        {{ __('mfw-mediaclass.labels.video_width_full') }}
                                                    </option>
                                                </select>
                                                <input type="number" min="1" max="7680"
                                                    name="mediaclass[{{ $media->id }}][embed_width]"
                                                    class="form-control mediaclass-video-width"
                                                    value="{{ $embed_width === '100%' ? \MetaFramework\Mediaclass\Models\Media::DEFAULT_EMBED_WIDTH : $embed_width }}"
                                                    @disabled($embed_width_mode === 'full')>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-12">
                                            <label
                                                class="form-label">{{ __('mfw-mediaclass.labels.video_height') }}</label>
                                            <input type="number" min="1" max="4320"
                                                name="mediaclass[{{ $media->id }}][embed_height]"
                                                class="form-control" value="{{ $embed_height }}">
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @foreach ($mediaLocales as $locale)
                                @php
                                    $descriptionName = $is_bridge
                                        ? 'mediaclass_bridge[' .
                                            $group .
                                            '][' .
                                            $media_key .
                                            '][description][' .
                                            $locale .
                                            ']'
                                        : 'mediaclass[' . $media->id . '][description][' . $locale . ']';
                                @endphp
                                <div class="col-12 description {{ !$description ? 'd-none' : '' }}">
                                    <x-mfw-inputable::textarea name="{{ $descriptionName }}" :height="100"
                                        class="description mt-2" :value="$media->description[$locale] ?? ''"
                                        label="{{ __('mfw-mediaclass.labels.description') }} ({{ $locale }})" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="mediaclass-alerts mt-2" data-msg="{{ $nomedia }}">
    @if ($medias->isEmpty())
        <x-mfw-support::alert type="warning" :message="$nomedia" />
    @endif
</div>

@pushonce('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/css/lightgallery-bundle.min.css"
        integrity="sha512-fXavT4uA4L0uTUFHC275D7zd751ohbSuD6VUMc5JysWfmR+NxTI3w7etE7N9hjTETcoh0w0V+24Cel4xXnqvCg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
@endpushonce

@pushonce('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/lightgallery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/plugins/zoom/lg-zoom.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/plugins/thumbnail/lg-thumbnail.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/plugins/video/lg-video.min.js"></script>
@endpushonce
