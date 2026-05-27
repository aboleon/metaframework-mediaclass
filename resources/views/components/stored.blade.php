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
                $cropableImg = $is_bridge || !$is_image ? null : new \MetaFramework\Mediaclass\Cropable($media);
                $cropableImg?->setCropableFromComponent($cropable);
                $preview = match (true) {
                    $is_image => $media->url($cropableImg?->isCropped() ? 'cropped' : 'sm'),
                    $is_video => asset('vendor/mfw-mediaclass/images/files/mov.png'),
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
                data-bridge="{{ $is_bridge ? '1' : '0' }}" id="mediaclass-{{ $media_dom_id }}">
                @if ($is_bridge)
                    <input type="hidden" name="mediaclass_bridge[{{ $group }}][{{ $media_key }}][id]"
                        value="{{ $media->id }}">
                @endif
                <span class="unlink"><i class="bi bi-x-circle-fill"></i></span>
                <div class="row m-0">
                    <div class="col-xl-3 pe-xl-4 col-12 impImg position-relative preview {{ $previewType }}">
                        <div class="w-100 h-100"
                            style="background-image: url({{ $preview }});background-size: contain;background-repeat: no-repeat;background-position: center;">
                            <div class="actions">
                                @if ($is_image)
                                    {{-- LightGallery trigger --}}
                                    <a href="{{ $fullSizeUrl }}" class="lightgallery-item zoom"
                                        data-sub-html="<h4>{{ $media->original_filename }}</h4><p>{{ $media->description[app()->getLocale()] ?? '' }}</p>">
                                        <i class="bi bi-zoom-in"></i>
                                    </a>
                                @else
                                    <a target="_blank" href="{{ $media->url() }}" class="zoom">
                                        <i class="bi bi-zoom-in"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
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

{{-- Include LightGallery CSS and JS once --}}
@pushonce('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/css/lightgallery-bundle.min.css"
        integrity="sha512-fXavT4uA4L0uTUFHC275D7zd751ohbSuD6VUMc5JysWfmR+NxTI3w7etE7N9hjTETcoh0w0V+24Cel4xXnqvCg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        /* Custom styles for lightgallery integration */
        .lightgallery-item {
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .lightgallery-item:hover {
            transform: scale(1.1);
        }

        /* Hide non-image items from lightgallery */
        .lightgallery-container .file {
            pointer-events: none;
        }

        .lightgallery-container .file .lightgallery-item {
            display: none;
        }
    </style>
@endpushonce

@pushonce('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/lightgallery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/plugins/zoom/lg-zoom.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/plugins/thumbnail/lg-thumbnail.min.js"></script>
    <script>
        // Initialize LightGallery on page load for existing images
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.lightgallery-container').forEach(container => {
                const imageLinks = container.querySelectorAll('.lightgallery-item');
                if (imageLinks.length > 0) {
                    lightGallery(container, {
                        selector: '.lightgallery-item',
                        speed: 500,
                        download: true,
                        counter: true,
                        zoom: true,
                        thumbnail: imageLinks.length > 1,
                        plugins: [lgZoom, lgThumbnail],
                        mobileSettings: {
                            controls: true,
                            showCloseIcon: true,
                            download: true
                        }
                    });
                }
            });
        });
    </script>
@endpushonce
