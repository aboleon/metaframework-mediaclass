// @ts-nocheck

const MediaclassManager = {
    uploadable(selector) {
        return selector.closest('.mediaclass-uploadable');
    },
    deleteCropForm() {
        return $('#mediaclass-delete-crop-form');
    },
    confirmDeleteModal() {
        return $('#mediaclass-confirm-delete');
    },
    confirmDeleteBtn() {
        return $('#confirm-delete-btn');
    },
    alerts(uploadable = null) {
        return uploadable ? uploadable.find('.mediaclass-alerts').first() : $('.mediaclass-alerts');
    },
    syncEmptyState(uploadable) {
        const alertsContainer = this.alerts(uploadable);

        alertsContainer.find('[data-mediaclass-empty-state]').remove();

        if (uploadable.find('.uploaded div.mediaclass.unlinkable').length > 0) {
            return;
        }

        const message = String(alertsContainer.attr('data-msg') || '');

        if (!message) {
            return;
        }

        $('<div>', {
            'data-mediaclass-empty-state': '',
            class: 'mediaclass-empty-state'
        })
            .append($('<div>', {
                class: 'alert alert-warning',
                text: message
            }))
            .appendTo(alertsContainer);
    },
    lightGalleryContainer(uploadable) {
        let container = uploadable.find('.lightgallery-container').first();

        if (container.length > 0) {
            return container;
        }

        const grid = Math.min(Math.max(Number(uploadable.data('grid')) || 1, 1), 4);
        container = $('<div>', {
            id: `lightgallery-${uploadable.data('group')}-${uploadable.data('model-id')}`,
            class: `lightgallery-container mediaclass-stored-grid mediaclass-stored-grid--${grid}`,
            'data-grid': grid
        });

        const alertsContainer = this.alerts(uploadable);

        if (alertsContainer.length > 0) {
            container.insertBefore(alertsContainer);
        } else {
            uploadable.find('.uploaded').first().append(container);
        }

        return container;
    },
    setVeil(container) {
        const $container = container instanceof jQuery ? container : $(container);
        this.removeVeil($container);
        $container.prepend('<div class="veil" style="border-radius:25px"><img class="loading" src="/vendor/mfw-mediaclass/loading.svg" width="40" alt="..."></div>');
    },
    removeVeil(container = null) {
        if (container) {
            const $container = container instanceof jQuery ? container : $(container);
            $container.find('> .veil').remove();
            return;
        }
        $('.veil').remove();
    },
    positionNames: ['left', 'up', 'down', 'right'],

    csrfToken() {
        return $('meta[name="csrf-token"]').attr('content');
    },
    lightGalleryOptions(enableThumbnails) {
        return {
            selector: '.lightgallery-item',
            speed: 500,
            download: true,
            counter: true,
            zoom: true,
            thumbnail: enableThumbnails,
            exThumbImage: 'data-thumb',
            autoplayFirstVideo: true,
            autoplayVideoOnSlide: true,
            plugins: [lgZoom, lgThumbnail, lgVideo],
            mobileSettings: {
                controls: true,
                showCloseIcon: true,
                download: true
            }
        };
    },
    refreshLightGallery(container) {
        const $container = container instanceof jQuery ? container : $(container);
        const element = $container.get(0);

        if (!element || typeof window.lightGallery !== 'function') {
            return;
        }

        const instance = element.mediaclassLightGallery || $container.data('lightGallery');
        if (instance && typeof instance.destroy === 'function') {
            instance.destroy();
        }

        const galleryItems = $container.find('.lightgallery-item');
        element.mediaclassLightGallery = galleryItems.length > 0
            ? lightGallery(element, this.lightGalleryOptions(galleryItems.length > 1))
            : null;
    },
    mediaOrder(container) {
        return container
            .children('.uploaded-image[data-bridge="0"]')
            .map(function () {
                return Number($(this).attr('data-id'));
            })
            .get();
    },
    restoreMediaOrder(container, mediaIds) {
        mediaIds.forEach((mediaId) => {
            const item = container.children(`.uploaded-image[data-id="${mediaId}"][data-bridge="0"]`).first();

            if (item.length) {
                container.append(item);
            }
        });

        this.refreshLightGallery(container);
    },
    resetFlowAssignments(uploadable, container, result) {
        container.children('.uploaded-image[data-bridge="0"]').each(function (index) {
            const media = $(this);
            const positions = media.find('.positions').first();

            media.attr('data-sort-order', index + 1);
            positions.find('[data-position]').removeClass('active');
            positions.find('[data-position="left"]').addClass('active');
            positions.find('input[type="hidden"]').val('left');
            media.find('[data-mediaclass-subgroup-select]')
                .val('')
                .attr('data-saved-value', '');
        });

        uploadable.attr('data-subgroup-values', '{}');
        $(document).trigger('mediaclass:subgroup-saved', [result, uploadable, null]);
        $(document).trigger('mediaclass:reordered', [result, uploadable, container]);
    },
    persistMediaOrder(uploadable, container, previousOrder) {
        const mediaIds = this.mediaOrder(container);

        if (mediaIds.join(',') === previousOrder.join(',')) {
            return;
        }

        this.setVeil(uploadable);

        $.ajax({
            url: uploadable.attr('data-ajax'),
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken()
            },
            data: {
                action: 'reorder',
                model: uploadable.attr('data-model') || '',
                model_id: uploadable.attr('data-model-id') || '',
                group: uploadable.attr('data-group') || '',
                ghost: uploadable.attr('data-ghost') || '0',
                media_ids: mediaIds
            },
            success: (result) => {
                if (result.error || result.abort) {
                    this.restoreMediaOrder(container, previousOrder);
                    this.printResponseMessages(uploadable, result);

                    return;
                }

                if (result.changed) {
                    this.resetFlowAssignments(uploadable, container, result);
                }

                this.refreshLightGallery(container);
                this.printResponseMessages(uploadable, result);
            },
            error: () => {
                this.restoreMediaOrder(container, previousOrder);

                const message = this.i18nText(
                    uploadable,
                    'reorder_failed',
                    'The media order could not be saved.'
                );
                notificator(200, {danger: [message]}, this.alerts(uploadable), false, {isDismissable: true});
            },
            complete: () => {
                this.removeVeil(uploadable);
            }
        });
    },
    syncSortable(uploadable) {
        const container = uploadable.find('.lightgallery-container').first();

        if (!container.length || typeof window.Sortable === 'undefined') {
            return;
        }

        const element = container.get(0);
        const nativeMedia = container.children('.uploaded-image[data-bridge="0"]');
        const hasBridgeMedia = container.children('.uploaded-image[data-bridge="1"]').length > 0;
        const subgroup = String(uploadable.attr('data-subgroup') || '');
        const hasFixedSubgroup = subgroup !== '' && subgroup !== 'false';
        const hasStorableFilters = uploadable.find('input[name^="mediaclass_storable["]').length > 0;
        const enabled = nativeMedia.length > 1
            && !hasBridgeMedia
            && !hasFixedSubgroup
            && !hasStorableFilters;

        container.toggleClass('mediaclass-sortable-enabled', enabled);

        if (!enabled) {
            container.off('keydown.mediaclassSort');

            if (element.mediaclassSortable) {
                element.mediaclassSortable.destroy();
                element.mediaclassSortable = null;
            }

            return;
        }

        container
            .off('keydown.mediaclassSort')
            .on('keydown.mediaclassSort', '.mediaclass-sort-handle', (event) => {
                if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) {
                    return;
                }

                const item = $(event.currentTarget).closest('.uploaded-image[data-bridge="0"]');
                const target = event.key === 'ArrowLeft'
                    ? item.prevAll('.uploaded-image[data-bridge="0"]').first()
                    : item.nextAll('.uploaded-image[data-bridge="0"]').first();

                if (!target.length) {
                    return;
                }

                event.preventDefault();

                const previousKeyboardOrder = this.mediaOrder(container);
                if (event.key === 'ArrowLeft') {
                    item.insertBefore(target);
                } else {
                    item.insertAfter(target);
                }

                this.persistMediaOrder(uploadable, container, previousKeyboardOrder);
                event.currentTarget.focus();
            });

        if (element.mediaclassSortable) {
            return;
        }

        let previousOrder = [];

        element.mediaclassSortable = Sortable.create(element, {
            animation: 150,
            draggable: '.uploaded-image[data-bridge="0"]',
            handle: '.mediaclass-sort-handle',
            ghostClass: 'mediaclass-sort-ghost',
            chosenClass: 'mediaclass-sort-chosen',
            dragClass: 'mediaclass-sort-drag',
            onStart: () => {
                previousOrder = this.mediaOrder(container);
            },
            onEnd: () => {
                this.persistMediaOrder(uploadable, container, previousOrder);
            }
        });
    },
    actionsWithoutLinks(actions) {
        actions.find('a').each(function () {
            $(this).replaceWith($(this).contents());
        });

        return actions;
    },
    backgroundImageUrl(style) {
        const match = (style || '').match(/background-image:\s*url\((['"]?)(.*?)\1\)/i);

        return match ? match[2] : '';
    },
    escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    },
    html5VideoMimeType(url) {
        let path = '';

        try {
            path = new URL(url, window.location.href).pathname;
        } catch {
            return null;
        }

        const match = path.match(/\.(mp4|m4v|webm|ogv|ogg)(?:$|[_-])/i);

        if (!match) {
            return null;
        }

        return {
            mp4: 'video/mp4',
            m4v: 'video/mp4',
            webm: 'video/webm',
            ogv: 'video/ogg',
            ogg: 'video/ogg'
        }[match[1].toLowerCase()];
    },
    html5VideoData(url) {
        const mimeType = this.html5VideoMimeType(url);

        return mimeType ? JSON.stringify({
            source: [{ src: url, type: mimeType }],
            attributes: { preload: 'metadata', playsinline: true, controls: true }
        }) : null;
    },
    getI18n(uploadable) {
        if (!uploadable || uploadable.length < 1) {
            return {};
        }
        const i18n = uploadable.data('i18n');
        return i18n && typeof i18n === 'object' ? i18n : {};
    },
    i18nText(uploadable, key, fallback = '') {
        const i18n = this.getI18n(uploadable);
        const value = Object.prototype.hasOwnProperty.call(i18n, key) ? i18n[key] : '';
        return value !== undefined && value !== null && String(value).length > 0 ? value : fallback;
    },
    interpolate(template, params = {}) {
        if (!template) return '';
        let output = String(template);
        Object.keys(params).forEach((key) => {
            output = output.replace(new RegExp(`:${key}\\b`, 'g'), params[key]);
        });
        return output;
    },
    responseMessages(data) {
        const messages = data.mfw_ajax_messages || data.messages || [];

        return Array.isArray(messages) ? messages : [];
    },
    printResponseMessages(uploadable, data) {
        const messages = this.responseMessages(data);

        if (messages.length < 1) {
            return;
        }

        notificator(200, messages, this.alerts(uploadable));
    },
    locale() {
        return document.documentElement.lang || navigator.language || 'en';
    },
    executeCallback(uploadable, data) {
        const callbackName = uploadable.data('callback');

        if (callbackName && typeof window[callbackName] === 'function') {
            try {
                window[callbackName](data);
            } catch (error) {
                console.error(`Error executing upload callback '${callbackName}':`, error);
            }
        }
    },

    isLimitReached(uploadable) {
        const limit = Number(uploadable.data('limit'));
        if (limit <= 0) {
            return false;
        }

        const currentCount = uploadable.find('.uploaded div.mediaclass.unlinkable').length;
        return currentCount >= limit;
    },

    unlinkable() {
        $(document).off('click.unlink').on('click.unlink', '.unlink', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $unlinkBtn = $(this);
            const selector = $unlinkBtn.closest('.unlinkable');
            const uploadable = MediaclassManager.uploadable($unlinkBtn);
            const container = selector.closest('.uploaded');

            const deleteData = {
                selector: selector,
                container: container,
                uploadable: uploadable,
                formData: MediaclassManager.deleteFormData(selector, uploadable)
            };

            const $modal = MediaclassManager.confirmDeleteModal();

            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');

            try {
                $modal.modal('show');
            } catch (error) {
                console.error('Error showing modal:', error);
            }

            MediaclassManager.confirmDeleteBtn()
                .off('click.mediaclassDelete')
                .on('click.mediaclassDelete', function () {
                    MediaclassManager.confirmDeleteModal().modal('hide');

                    const ajaxUrl = String(deleteData.uploadable.attr('data-ajax') || '');

                    if (!ajaxUrl) {
                        console.error('Mediaclass delete aborted: missing data-ajax URL.');
                        return;
                    }

                    MediaclassManager.setVeil(deleteData.selector);
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        dataType: 'json',
                        data: deleteData.formData,
                        headers: {
                            'X-CSRF-TOKEN': MediaclassManager.csrfToken(),
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).done(function (response) {
                        MediaclassManager.printResponseMessages(deleteData.uploadable, response);

                        if (
                            !response
                            || response.error
                            || response.abort
                            || String(response.deleted_id ?? '') !== String(deleteData.selector.attr('data-id') ?? '')
                        ) {
                            return;
                        }

                        deleteData.selector.remove();
                        MediaclassManager.syncDetailsSaveButton(deleteData.uploadable);
                        MediaclassManager.syncSortable(deleteData.uploadable);
                        MediaclassManager.syncEmptyState(deleteData.uploadable);
                    }).always(function () {
                        MediaclassManager.removeVeil(deleteData.selector);
                    });
                });
        });
    },

    deleteFormData(selector, uploadable) {
        const isBridge = String(selector.attr('data-bridge') || '') === '1';

        if (isBridge) {
            return $.param({
                action: 'deleteBridge',
                id: selector.attr('data-id'),
                model: uploadable.attr('data-model'),
                model_id: uploadable.attr('data-model-id'),
                group: uploadable.attr('data-group'),
                subgroup: uploadable.attr('data-subgroup') || ''
            });
        }

        return $.param({
            action: 'delete',
            id: selector.attr('data-id'),
            model: uploadable.attr('data-model'),
            model_id: uploadable.attr('data-model-id'),
            group: uploadable.attr('data-group'),
            subgroup: uploadable.attr('data-subgroup') || '',
            ghost: uploadable.attr('data-ghost') || '0',
            mediaclass_temp_id: $('input[name="mediaclass_temp_id"]').first().val() || ''
        });
    },

    saveDescriptionsFormData(uploadable) {
        const payload = uploadable
            .find('.uploaded')
            .find([
                'input[name^="mediaclass["]',
                'select[name^="mediaclass["]',
                'textarea[name^="mediaclass["]',
                'input[name^="mediaclass_bridge["]',
                'select[name^="mediaclass_bridge["]',
                'textarea[name^="mediaclass_bridge["]'
            ].join(','))
            .serializeArray();

        payload.push(
            {name: 'action', value: 'saveDescriptions'},
            {name: 'model', value: uploadable.attr('data-model') || ''},
            {name: 'model_id', value: uploadable.attr('data-model-id') || ''},
            {name: 'group', value: uploadable.attr('data-group') || ''},
            {name: 'ghost', value: uploadable.attr('data-ghost') || '0'}
        );

        const subgroup = uploadable.attr('data-subgroup') || '';
        if (subgroup && subgroup !== 'false') {
            payload.push({name: 'subgroup', value: subgroup});
        }

        return $.param(payload);
    },

    bindDescriptionSave() {
        $(document)
            .off('click.mediaclassDescriptions', '.mediaclass-save-descriptions')
            .on('click.mediaclassDescriptions', '.mediaclass-save-descriptions', function (event) {
                event.preventDefault();

                const uploadable = $(this).closest('.mediaclass-uploadable');
                const formData = MediaclassManager.saveDescriptionsFormData(uploadable);

                mfwAjax(formData, uploadable, {
                    spinner: true,
                    lockForm: true
                });
            });
    },

    syncDetailsSaveButton(uploadable) {
        const hasDescriptions = Number(uploadable.attr('data-has-description')) === 1;
        const hasVideos = uploadable.find('.uploaded .preview.video').length > 0;

        uploadable
            .find('.mediaclass-save-descriptions')
            .toggleClass('d-none', !hasDescriptions && !hasVideos);
    },

    syncVideoWidthMode(select) {
        const widthInput = select.closest('.input-group').find('.mediaclass-video-width').first();

        widthInput.prop('disabled', select.val() === 'full');
    },
    syncVideoHeightMode(select) {
        const heightInput = select.closest('.input-group').find('.mediaclass-video-height').first();

        heightInput.prop('disabled', select.val() === 'auto');
    },

    videoDimensionsFields(uploadable, namePrefix = '', storable = {}) {
        const widthLabel = this.i18nText(uploadable, 'video_width_label', 'Video width');
        const heightLabel = this.i18nText(uploadable, 'video_height_label', 'Video height');
        const autoHeightLabel = this.i18nText(uploadable, 'video_height_auto', 'Auto');
        const pixelsLabel = this.i18nText(uploadable, 'video_width_pixels', 'Pixels');
        const fullWidthLabel = this.i18nText(uploadable, 'video_width_full', '100% width');
        const storedWidth = String(storable.embed_width ?? '560').trim();
        const isFullWidth = storedWidth === '100%';
        const pixelWidth = /^\d+$/.test(storedWidth) ? storedWidth : '560';
        const storedHeight = String(storable.embed_height ?? 'auto').trim().toLowerCase();
        const isAutoHeight = storedHeight === 'auto' || !/^\d+$/.test(storedHeight);
        const pixelHeight = /^\d+$/.test(storedHeight) ? storedHeight : '315';
        const fieldName = (name) => namePrefix ? `${namePrefix}[${name}]` : name;

        return `
            <div class="col-12 mediaclass-video-dimensions">
                <div class="mediaclass-video-dimensions__grid">
                    <div class="mediaclass-video-dimension">
                        <label class="form-label">${widthLabel}</label>
                        <div class="input-group">
                            <select name="${fieldName('embed_width_mode')}" class="form-select mediaclass-video-width-mode">
                                <option value="pixels"${isFullWidth ? '' : ' selected'}>${pixelsLabel}</option>
                                <option value="full"${isFullWidth ? ' selected' : ''}>${fullWidthLabel}</option>
                            </select>
                            <input type="number" min="1" max="7680"
                                   name="${fieldName('embed_width')}"
                                   class="form-control mediaclass-video-width"
                                   value="${pixelWidth}"${isFullWidth ? ' disabled' : ''}>
                        </div>
                    </div>
                    <div class="mediaclass-video-dimension">
                        <label class="form-label">${heightLabel}</label>
                        <div class="input-group">
                            <select name="${fieldName('embed_height_mode')}" class="form-select mediaclass-video-height-mode">
                                <option value="auto"${isAutoHeight ? ' selected' : ''}>${autoHeightLabel}</option>
                                <option value="pixels"${isAutoHeight ? '' : ' selected'}>${pixelsLabel}</option>
                            </select>
                            <input type="number" min="1" max="4320"
                                   name="${fieldName('embed_height')}"
                                   class="form-control mediaclass-video-height"
                                   value="${pixelHeight}"${isAutoHeight ? ' disabled' : ''}>
                        </div>
                    </div>
                </div>
            </div>`;
    },

    bindVideoDimensions() {
        $(document)
            .off('change.mediaclassVideoWidth', '.mediaclass-video-width-mode')
            .on('change.mediaclassVideoWidth', '.mediaclass-video-width-mode', function () {
                MediaclassManager.syncVideoWidthMode($(this));
            });

        $(document)
            .off('change.mediaclassVideoHeight', '.mediaclass-video-height-mode')
            .on('change.mediaclassVideoHeight', '.mediaclass-video-height-mode', function () {
                MediaclassManager.syncVideoHeightMode($(this));
            });
    },

    positions(uploadable) {
        uploadable
            .find('.positions i[data-position]')
            .off('click.mediaclassPosition')
            .on('click.mediaclassPosition', function () {
                const positionButton = $(this);
                const positionsContainer = positionButton.closest('.positions');

                positionsContainer.find('i[data-position]').removeClass('active');
                positionButton.addClass('active');
                positionsContainer.find('input').val(positionButton.data('position'));
            });
    },


    appendUploadedMedia(uploadable, data, hideDescription) {
        const html = this.buildUploadedFileHTML(data, hideDescription, uploadable);
        const lightGalleryContainer = this.lightGalleryContainer(uploadable);

        lightGalleryContainer.append(html);
        this.syncEmptyState(uploadable);
        this.unlinkable();
        this.positions(uploadable);
        this.syncDetailsSaveButton(uploadable);
        this.syncSortable(uploadable);

        setTimeout(() => {
            this.refreshLightGallery(lightGalleryContainer);
        }, 100);
    },

    buildUploadedFileHTML(data, hideDescription, uploadable) {
        const {uploaded, filetype, preview, link, cropable_links, has_positions} = data;
        const locale = this.locale();
        const uploadedAtTemplate = this.i18nText(uploadable, 'uploaded_at');
        const uploadedAtText = this.interpolate(uploadedAtTemplate, {
            date: new Date(uploaded.created_at).toLocaleDateString(locale),
            time: new Date(uploaded.created_at).toLocaleTimeString(locale, {
                hour: '2-digit',
                minute: '2-digit'
            })
        });
        const positionsLabel = this.i18nText(uploadable, 'positions_label');
        const descriptionLabel = this.i18nText(uploadable, 'description_label');
        const sortHandleLabel = this.i18nText(uploadable, 'sort_handle', 'Change media order');

        // For images, we need to get the full size URL for LightGallery
        const fullSizeUrl = filetype === 'image' ? (data.urls && data.urls.xl ? data.urls.xl : link) : link;

        let html = `
<div class="mediaclass unlinkable uploaded-image my-2" data-id="${uploaded.id}" data-bridge="0" data-sort-order="${uploaded.sort_order || 0}" id="mediaclass-${uploaded.id}">
    <span class="mediaclass-sort-handle" role="button" tabindex="0" title="${sortHandleLabel}" aria-label="${sortHandleLabel}">
        <i class="bi bi-grip-vertical"></i>
    </span>
    <span class="unlink"><i class="bi bi-x-circle-fill"></i></span>
    <div class="row m-0">
        <div class="col-xl-3 pe-xl-4 col-12 impImg position-relative preview ${filetype}">`;

        if (filetype === 'image') {
            // For images: make the entire preview area clickable with LightGallery
            html += `
            <a href="${fullSizeUrl}"
               class="lightgallery-item d-block w-100 h-100"
               data-thumb="${preview}"
               data-sub-html="<h4>${uploaded.original_filename}</h4><p>${uploaded.description ? (uploaded.description[document.documentElement.lang] || '') : ''}</p>"
               style="background-image: url(${preview}); background-size: contain; background-repeat: no-repeat; background-position: center;">
                <div class="actions">
                    <i class="bi bi-zoom-in"></i>
                </div>
            </a>`;
        } else if (filetype === 'video') {
            const storable = uploaded.storable || {};
            const embedWidth = Number(storable.embed_width) > 0 ? Number(storable.embed_width) : 560;
            const embedHeight = Number(storable.embed_height) > 0 ? Number(storable.embed_height) : 'auto';
            const videoSizeAttribute = Number.isInteger(embedHeight)
                ? `data-lg-size="${embedWidth}-${embedHeight}"`
                : '';
            const html5VideoData = this.html5VideoData(link);
            const videoSourceAttributes = html5VideoData
                ? `data-video='${this.escapeHtml(html5VideoData)}'`
                : `href="${this.escapeHtml(link)}" data-src="${this.escapeHtml(link)}"`;

            html += `
            <a ${videoSourceAttributes}
               data-poster="${preview}"
               data-thumb="${preview}"
               data-download-url="false"
               ${videoSizeAttribute}
               data-sub-html="<h4>${uploaded.original_filename}</h4><p>${uploaded.description ? (uploaded.description[document.documentElement.lang] || '') : ''}</p>"
               class="lightgallery-item d-block w-100 h-100"
               style="background-image: url(${preview}); background-size: cover; background-repeat: no-repeat; background-position: center;">
                <div class="actions">
                    <i class="bi bi-play-circle-fill"></i>
                </div>
            </a>`;
        } else {
            // For non-images (PDFs, etc): make the entire preview area clickable but open in new tab
            html += `
            <a href="${link}"
               target="_blank"
               class="file-preview-link d-block w-100 h-100"
               style="background-image: url(${preview}); background-size: contain; background-repeat: no-repeat; background-position: center;">
                <div class="actions">
                    <i class="bi bi-zoom-in"></i>
                </div>
            </a>`;
        }

        html += `
        </div>
        <div class="col-xl-9 col-12 impFileName">
            <div class="row infos">
                <div class="col-sm-12">
                    <p class="name">
                        <span class="filename rounded-1 py-1 px-2 text-bg-secondary">${uploaded.original_filename}</span>
                        <span class="uploaded-at bg-light-subtle text-dark opacity-75">
                            ${uploadedAtText}
                        </span>
                    </p>
                </div>
            </div>
            ${filetype === 'image' && cropable_links ? cropable_links : ''}
            <div class="row params mt-3">
                <div class="col-12 positions text-center ps-2${has_positions === true ? '' : ' d-none'}">
                    <b>${positionsLabel}</b>
                    <div class="choices pt-2">`;

        // Add position buttons
        for (const position of this.positionNames) {
            const isActive = uploaded.position === position ? ' active' : '';
            html += `<i class="bi bi-arrow-${position}-square-fill${isActive}" data-position="${position}"></i>`;
        }

        html += `
                        <input type="hidden" name="mediaclass[${uploaded.id}][position]" value="${uploaded.position || 'left'}">
                    </div>
                </div>`;

        if (filetype === 'video') {
            html += this.videoDimensionsFields(uploadable, `mediaclass[${uploaded.id}]`, uploaded.storable || {});
        }

        // Add descriptions
        const descriptions = uploaded.description || {};
        for (const [key, value] of Object.entries(descriptions)) {
            html += `
                <div class="col-12 description ${hideDescription ? ' d-none' : ''}">
                    <div class="mt-1">
                        <label class="form-label">${descriptionLabel} (${key})</label>
                        <textarea name="mediaclass[${uploaded.id}][description][${key}]"
                                class="form-control description"
                                rows="3">${value || ''}</textarea>
                    </div>
                </div>`;
        }

        html += `
            </div>
        </div>
    </div>
</div>`;

        return html;
    },

    modalCrop() {
        const $modal = $('#mediaclass-crop');

        $(document).off('click.mediaclassCrop', '.crop-actions-bar .crop');
        $(document).off('ajaxSuccess.mediaclassCrop');
        $modal.off('click.mediaclassCrop', '#mediaclass-delete-crop-btn');
        $modal.off('hidden.bs.modal.mediaclassCrop');

        $(document).on('click.mediaclassCrop', '.crop-actions-bar .crop', function (e) {
            e.preventDefault();
            const $btn = $(this);
            const isView = $btn.hasClass('cropped');
            const ajaxUrl = String(MediaclassManager.uploadable($btn).attr('data-ajax') || '');

            $modal.find('.modal-body').empty();

            if (isView) {
                const template = $('#mediaclass-crop-view-template').html();
                $modal.find('.modal-body').html(template);

                setTimeout(() => {
                    const cropLabel = $btn.data('crop-label') || $btn.data('crop-key');

                    $modal.find('.crop-key-title').text(cropLabel);
                    $modal.find('.crop-key-label').text($btn.data('crop-key'));
                    $modal.find('.crop-dimensions-text, .crop-dimensions-label')
                        .text($btn.data('crop-w') + ' x ' + $btn.data('crop-h'));
                    $modal.find('.crop-preview-image')
                        .attr('src', $btn.data('preview-url'));

                    const filename = $btn.closest('.mediaclass').find('.name span:first').text();
                    $modal.find('.crop-filename').text($btn.data('crop-key') + '_' + filename);

                    const $form = $modal.find('#mediaclass-delete-crop-form');
                    $form.attr('data-ajax', ajaxUrl);
                    $form.attr('data-media-id', $btn.data('media-id'));
                    $form.attr('data-crop-key', $btn.data('crop-key'));

                    const $container = $modal.find('.crop-view-container');
                    const $img = $modal.find('.crop-preview-image');
                    const setContainerWidth = () => {
                        const modalBody = $modal.find('.modal-body').get(0);
                        const style = modalBody ? getComputedStyle(modalBody) : null;
                        const paddingLeft = style ? parseFloat(style.paddingLeft) || 0 : 0;
                        const paddingRight = style ? parseFloat(style.paddingRight) || 0 : 0;
                        const paddingX = paddingLeft + paddingRight;
                        const viewportMax = Math.max(0, (window.innerWidth || 1140) - 32);
                        const maxDialogWidth = Math.min(1140, viewportMax);
                        const maxContentWidth = Math.max(0, maxDialogWidth - paddingX);
                        const naturalWidth = $img.get(0)?.naturalWidth || 0;
                        const contentWidth = Math.min(maxContentWidth, naturalWidth || maxContentWidth);
                        const dialogWidth = contentWidth + paddingX;
                        $container.css('width', `${contentWidth}px`);
                        $modal.find('.mediaclass-crop-dialog').css('width', `${dialogWidth}px`);
                    };
                    $img.off('load.mediaclassCrop').on('load.mediaclassCrop', setContainerWidth);
                    if ($img.get(0)?.complete) {
                        setContainerWidth();
                    }
                }, 50);

                $modal.modal('show');
            } else {
                $modal.find('.modal-body').load($btn.attr('href'), function () {
                    $modal.modal('show');
                });
            }
        });

        $modal.on('click.mediaclassCrop', '#mediaclass-delete-crop-btn', function () {
            const c = MediaclassManager.deleteCropForm();
            mfwAjax('action=deleteCrop&media_id=' + c.attr('data-media-id') + '&crop_key=' + c.attr('data-crop-key'), $(c));
        });

        $(document).on('ajaxSuccess.mediaclassCrop', function (_e, xhr) {
            const ct = (xhr.getResponseHeader('Content-Type') || '').toLowerCase();
            if (!ct.includes('application/json')) return;

            try {
                const res = JSON.parse(xhr.responseText);
                if (res.action === 'delete_crop') {
                    MediaclassManager.deletedCrop(res);
                }
            } catch (e) {
                console.error('Error parsing JSON response', e);
            }
        });

        $modal.on('hidden.bs.modal.mediaclassCrop', function () {
            $(this).find('.modal-body').empty();
            $(this).find('.mediaclass-crop-dialog').css('width', '');
        });
    },

    hideModal() {
        setTimeout(() => {
            const $modalCrop = $('#mediaclass-crop');
            $modalCrop.modal('hide');
        }, 1500);
    },

    cropped: function (result) {
        // Update the UI after cropping
        if (result.uploaded && result.uploaded.id) {
            const $mediaElement = $('#mediaclass-' + result.uploaded.id);

            // If we have a crop_key in the result, update that specific button
            if (result.crop_key) {
                const $cropButton = $mediaElement.find(`.crop[data-crop-key="${result.crop_key}"]`);

                if ($cropButton.length) {
                    // Add the 'cropped' class to indicate it's now cropped
                    $cropButton.addClass('cropped');

                    // Change the icon to the filled crop icon
                    $cropButton.find('i').first()
                        .removeClass('bi-scissors')
                        .addClass('bi-crop');

                    // Add the check mark icon if it doesn't exist
                    if (!$cropButton.find('.bi-check-circle-fill').length) {
                        $cropButton.append(' <i class="bi bi-check-circle-fill check-icon"></i>');
                    }

                    // Update the preview URL data attribute if we have the new URL
                    if (result.urls && result.urls.xl) {
                        $cropButton.attr('data-preview-url', result.urls.xl);
                    }
                }
            }

            // Original code for updating other elements
            if (result.cropable_links) {
                $mediaElement.find('.crop-actions-bar').replaceWith(result.cropable_links);
            }

            // Update the preview image if new URL provided
            if (result.urls && result.urls.xl) {
                $mediaElement.find('.preview').css('background-image', `url(${result.urls.xl})`);
                $mediaElement.find('.zoom').attr('href', result.urls.xl);
            }
        }

        MediaclassManager.hideModal();
    },

    // Method called after deleting a crop
    deletedCrop: function (result) {
        if (result.success && result.media_id && result.crop_key) {
            const $mediaElement = $('#mediaclass-' + result.media_id);

            // Find the specific crop button for this crop_key
            const $cropButton = $mediaElement.find(`.crop[data-crop-key="${result.crop_key}"]`);

            if ($cropButton.length) {
                // Remove the 'cropped' class to reset to uncropped state
                $cropButton.removeClass('cropped');

                // Change the icon from filled to regular crop icon
                $cropButton.find('i').first()
                    .removeClass('bi-crop')
                    .addClass('bi-scissors');

                // Remove the check mark icon if it exists
                $cropButton.find('.bi-check-circle-fill').remove();

                // Clear the preview URL data attribute
                $cropButton.attr('data-preview-url', '');

                // Update the href to point to the crop editor instead of just modal
                const baseHref = $cropButton.attr('href');
                if (baseHref && !baseHref.includes('?')) {
                    const width = $cropButton.attr('data-crop-w');
                    const height = $cropButton.attr('data-crop-h');
                    $cropButton.attr('href', `${baseHref}?w=${width}&h=${height}&crop_key=${result.crop_key}`);
                }
            }
        }

        MediaclassManager.hideModal();
    },

    fixModalLocation() {
        // Find all modals that might be inside tab panes
        const modals = ['#mediaclass-confirm-delete', '#mediaclass-crop'];

        modals.forEach(modalId => {
            const $modal = $(modalId);

            if ($modal.length > 0) {
                // Check if modal is inside a tab pane
                const $tabPane = $modal.closest('.tab-pane');

                if ($tabPane.length > 0) {
                    console.log(`Moving ${modalId} outside of tab structure`);

                    // Detach from current location and append to body
                    $modal.detach().appendTo('body');

                    // If the modal was inside a hidden tab, ensure it's properly hidden
                    $modal.removeClass('show').css({
                        'display': '',
                        'opacity': ''
                    });
                }
            }
        });
    },

    fixExistingFileLinks() {
        const manager = this;

        // Find all non-image preview areas (files like PDFs)
        $('.mediaclass .preview.file').each(function () {
            const $preview = $(this);
            const $previewDiv = $preview.find('> div').first();
            const $existingLink = $previewDiv.find('a.zoom');

            if ($existingLink.length > 0 && $previewDiv.length > 0) {
                // Get the href from the existing link
                const href = $existingLink.attr('href');

                // Get the background image style from the div
                const bgStyle = $previewDiv.attr('style');

                // Clone the actions div to preserve it
                const $actions = manager.actionsWithoutLinks($previewDiv.find('.actions').clone());

                // Create a new link that will replace the div
                const $newLink = $('<a>')
                    .attr('href', href)
                    .attr('target', '_blank')
                    .addClass('file-preview-link d-block w-100 h-100')
                    .attr('style', bgStyle);

                // Append the actions back to the new link
                $newLink.append($actions);

                // Replace the div with the new link
                $previewDiv.replaceWith($newLink);
            }
        });

        // Also fix image preview areas to have full clickable area
        $('.mediaclass .preview.image').each(function () {
            const $preview = $(this);
            const $previewDiv = $preview.find('> div').first();
            const $existingLink = $previewDiv.find('a.zoom');

            if ($existingLink.length > 0 && $previewDiv.length > 0) {
                // Get the href from the existing link
                const href = $existingLink.attr('href');

                // Get the background image style from the div
                const bgStyle = $previewDiv.attr('style');
                const thumbUrl = $existingLink.attr('data-thumb') || manager.backgroundImageUrl(bgStyle);

                // Get the description for lightgallery
                const $mediaElement = $preview.closest('.mediaclass');
                const filename = $mediaElement.find('.name span:first').text();
                const description = $mediaElement.find('textarea.description').first().val() || '';

                // Clone the actions div
                const $actions = manager.actionsWithoutLinks($previewDiv.find('.actions').clone());

                // Create new lightgallery link
                const $newLink = $('<a>')
                    .attr('href', href)
                    .addClass('lightgallery-item d-block w-100 h-100')
                    .attr('data-thumb', thumbUrl)
                    .attr('data-sub-html', `<h4>${filename}</h4><p>${description}</p>`)
                    .attr('style', bgStyle);

                // Append the actions
                $newLink.append($actions);

                // Replace the div with the new link
                $previewDiv.replaceWith($newLink);
            }
        });

        // Re-initialize LightGallery for image and video items.
        $('.lightgallery-container').each(function () {
            const $container = $(this);
            manager.refreshLightGallery($container);
        });
    },

    parseSubgroupJson(value) {
        try {
            return JSON.parse(String(value || '{}'));
        } catch (error) {
            return {};
        }
    },

    subgroupConfig(uploadable) {
        const options = this.parseSubgroupJson(uploadable.attr('data-subgroup-options'));

        if (Object.keys(options).length < 1) {
            return null;
        }

        return {
            options,
            values: this.parseSubgroupJson(uploadable.attr('data-subgroup-values')),
            label: String(uploadable.attr('data-subgroup-label') || 'Display group'),
            emptyLabel: String(uploadable.attr('data-subgroup-empty-label') || 'No subgroup')
        };
    },

    ensureSubgroupSelect(uploadedImage, uploadable, config) {
        const mediaId = String(uploadedImage.data('id') || '');

        if (
            !mediaId
            || String(uploadedImage.data('bridge') || '0') === '1'
            || uploadedImage.find('[data-mediaclass-subgroup-select]').length
            || !uploadedImage.find('.preview.image').length
        ) {
            return;
        }

        const selectedValue = String(config.values[mediaId] || '');
        const control = $('<div>', {
            class: 'col-12 mediaclass-subgroup ps-2 mb-2'
        });
        const label = $('<label>', {
            class: 'form-label fw-semibold mb-1',
            text: config.label
        });
        const select = $('<select>', {
            class: 'form-control form-control-sm',
            'data-mediaclass-subgroup-select': '1',
            'data-saved-value': selectedValue
        });

        select.append($('<option>', {
            value: '',
            text: config.emptyLabel
        }));

        Object.entries(config.options).forEach(([value, text]) => {
            select.append($('<option>', {value, text}));
        });

        select.val(selectedValue);
        control.append(label, select);

        const params = uploadedImage.find('.row.params').first();
        if (params.length) {
            params.prepend(control);

            return;
        }

        uploadedImage.find('.impFileName').first().append(control);
    },

    attachSubgroupSelects(uploadable) {
        const config = this.subgroupConfig(uploadable);

        if (!config) {
            return;
        }

        uploadable.find('.uploaded .mediaclass.uploaded-image').each((_, element) => {
            this.ensureSubgroupSelect($(element), uploadable, config);
        });
    },

    bindSubgroups() {
        $('.mediaclass-uploadable').each((_, element) => {
            const uploadable = $(element);
            const uploaded = uploadable.find('.uploaded').first();

            this.attachSubgroupSelects(uploadable);

            if (!window.MutationObserver || !uploaded.length || uploaded.get(0).mediaclassSubgroupObserver) {
                return;
            }

            const observer = new MutationObserver(() => this.attachSubgroupSelects(uploadable));
            observer.observe(uploaded.get(0), {
                childList: true,
                subtree: true
            });
            uploaded.get(0).mediaclassSubgroupObserver = observer;
        });

        $(document)
            .off('change.mediaclassSubgroup', '[data-mediaclass-subgroup-select]')
            .on('change.mediaclassSubgroup', '[data-mediaclass-subgroup-select]', (event) => {
                const select = $(event.currentTarget);
                const uploadedImage = select.closest('.mediaclass.uploaded-image');
                const uploadable = select.closest('.mediaclass-uploadable');
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
                    errorHandler: () => {
                        select.val(select.attr('data-saved-value') || '');

                        return true;
                    },
                    successHandler: (result) => {
                        const values = this.parseSubgroupJson(uploadable.attr('data-subgroup-values'));
                        const mediaId = String(result.media_id);
                        const subgroup = result.subgroup ? String(result.subgroup) : '';

                        if (subgroup) {
                            values[mediaId] = subgroup;
                        } else {
                            delete values[mediaId];
                        }

                        uploadable.attr('data-subgroup-values', JSON.stringify(values));
                        select.val(subgroup).attr('data-saved-value', subgroup);
                        $(document).trigger('mediaclass:subgroup-saved', [result, uploadable, select]);

                        return true;
                    }
                });
            });
    },

    init() {
        this.fixModalLocation();

        $('a[data-toggle="tab"], button[data-bs-toggle="tab"]').on('shown.bs.tab', () => {
            this.fixModalLocation();
        });

        this.fixExistingFileLinks();

        $('.mediaclass-uploadable').each(function () {
            MediaclassManager.positions($(this));
            MediaclassManager.syncDetailsSaveButton($(this));
            MediaclassManager.syncSortable($(this));

            $(this).find('.mediaclass-video-width-mode').each(function () {
                MediaclassManager.syncVideoWidthMode($(this));
            });
            $(this).find('.mediaclass-video-height-mode').each(function () {
                MediaclassManager.syncVideoHeightMode($(this));
            });
        });

        this.unlinkable();
        this.bindDescriptionSave();
        this.bindVideoDimensions();
        this.bindSubgroups();
        this.modalCrop();
    },
};

window.MediaclassManager = MediaclassManager;
window.mediaclassDeletedCrop = (result) => MediaclassManager.deletedCrop(result);
window.mediaclassCropped = (result) => MediaclassManager.cropped(result);

export function initMediaManager() {
    MediaclassManager.init();
}
