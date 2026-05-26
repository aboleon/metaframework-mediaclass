/*jshint esversion: 11 */

const MediaclassUploader = {
    // Cache common jQuery selectors
    template() {
        return $('#mediaclass-file-upload');
    },
    uploadable(selector) {
        return selector.closest('.mediaclass-uploadable');
    },
    uploadableContainer(selector) {
        return this.uploadable(selector).find('.mediaclass-upload-container').first();
    },
    fileupload(uploadContainer) {
        return uploadContainer.find('.mediaclass-fileupload').first();
    },
    messages(uploadable = null) {
        return uploadable ? uploadable.find('.mediaclass-messages').first() : $('.mediaclass-messages');
    },
    progress(uploadable = null) {
        return uploadable ? uploadable.find('.mediaclass-progress').first() : $('.mediaclass-progress');
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
    // Constants
    defaultFileSize: 16000000,
    positions_tags: ['left', 'up', 'down', 'right'],

    // Helper methods
    csrfToken() {
        return $('meta[name="csrf-token"]').attr('content');
    },
    calculateMaxFileSize(size) {
        if (!size || (!size.includes('KB') && !size.includes('MB'))) {
            return this.defaultFileSize;
        }

        const value = Number(size.replace(/\D+/g, ''));

        if (size.includes('KB')) {
            return value * 1024;
        }
        if (size.includes('MB')) {
            return value * 1024 * 1024;
        }

        return this.defaultFileSize;
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
    locale() {
        return document.documentElement.lang || navigator.language || 'en';
    },
    selectedMediaType(uploadable) {
        const selected = uploadable.find('.mediaclass-media-type:checked').first();

        return selected.length > 0 ? selected.val() : 'image';
    },
    mediaLocales(uploadable) {
        const locales = uploadable.data('media-locales');

        return Array.isArray(locales) && locales.length > 0 ? locales : [this.locale()];
    },
    isValidUrl(value) {
        try {
            const url = new URL(value);

            return ['http:', 'https:'].includes(url.protocol);
        } catch (error) {
            return false;
        }
    },

    // Execute callback if it exists
    executeCallback(uploadable, data) {
        const callbackName = uploadable.data('callback');

        if (callbackName && typeof window[callbackName] === 'function') {
            try {
                // Call the callback with the upload data
                window[callbackName](data);
            } catch (error) {
                console.error(`Error executing upload callback '${callbackName}':`, error);
            }
        }
    },

    // Check if uploader limit has been reached
    isLimitReached(uploadable) {
        const limit = Number(uploadable.data('limit'));
        if (limit <= 0) {
            return false; // No limit defined
        }

        const currentCount = uploadable.find('.uploaded div.mediaclass.unlinkable').length;
        return currentCount >= limit;
    },

    // Delete media
    unlinkable() {
        // Use event delegation to avoid re-binding issues
        $(document).off('click.unlink').on('click.unlink', '.unlink', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $unlinkBtn = $(this);
            const selector = $unlinkBtn.closest('.unlinkable');
            const uploadable = MediaclassUploader.uploadable($unlinkBtn);
            const container = selector.closest('.uploaded');

            // Store the delete data for use in the modal
            const deleteData = {
                selector: selector,
                container: container,
                uploadable: uploadable,
                formData: MediaclassUploader.deleteFormData(selector, uploadable)
            };

            const $modal = MediaclassUploader.confirmDeleteModal();

            // Clean up any existing modal states
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');

            try {
                // Show the confirmation modal
                $modal.modal('show');
            } catch (error) {
                console.error('Error showing modal:', error);
            }

            // Handle confirm button click
            MediaclassUploader.confirmDeleteBtn().off('click').on('click', function () {
                // Hide the modal first
                MediaclassUploader.confirmDeleteModal().modal('hide');

                // Perform the deletion
                MediaclassUploader.setVeil(deleteData.selector);
                mfwAjax(deleteData.formData, MediaclassUploader.template());

                $(document).off('ajaxSuccess.mediaclassDelete').on('ajaxSuccess.mediaclassDelete', function (_event, xhr) {
                    MediaclassUploader.removeVeil(deleteData.selector);
                    const response = xhr.responseJSON || MediaclassUploader.parseJsonResponse(xhr.responseText);

                    if (response && (response.error || response.abort)) {
                        $(document).off('ajaxSuccess.mediaclassDelete');
                        return;
                    }

                    deleteData.selector.remove();

                    if (deleteData.container.find('.unlinkable').length < 1) {
                        // Find the alerts container specific to this uploadable
                        const alertsContainer = deleteData.uploadable.find('.mediaclass-alerts').first();
                        alertsContainer.html(`<div class="alert alert-info">${alertsContainer.data('msg')}</div>`);
                    }

                    // Re-enable uploader button if we're now below the limit
                    if (!MediaclassUploader.isLimitReached(deleteData.uploadable)) {
                        deleteData.uploadable.find('span.mediaclass-uploader').removeClass('disabled');
                    }

                    // Remove this specific success handler
                    $(document).off('ajaxSuccess.mediaclassDelete');
                });

                $(document).off('ajaxError.mediaclassDelete').on('ajaxError.mediaclassDelete', function () {
                    MediaclassUploader.removeVeil(deleteData.selector);
                    $(document).off('ajaxError.mediaclassDelete');
                });
            });
        });
    },

    parseJsonResponse(responseText) {
        try {
            return JSON.parse(responseText);
        } catch (error) {
            return null;
        }
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
            model: uploadable.attr('data-model')
        });
    },

    uploaderCall() {
        $('span.mediaclass-uploader').off().on('click', function () {
            const instantiator = $(this).closest('.mediaclass-uploadable');
            const uploadContainer = MediaclassUploader.uploadableContainer($(this));
            const mediaType = MediaclassUploader.selectedMediaType(instantiator);

            // Check if we've reached the upload limit
            if (MediaclassUploader.isLimitReached(instantiator)) {
                // Optional: Show a message that the limit has been reached
                const limit = Number(instantiator.data('limit'));
                const limitTemplate = MediaclassUploader.i18nText(instantiator, 'limit_reached');
                const limitMessage = MediaclassUploader.interpolate(limitTemplate, {count: limit});
                MediaclassUploader.alerts(instantiator).html(`<div class="alert alert-warning">${limitMessage}</div>`);
                return; // Don't show the uploader
            }

            if (mediaType === 'video') {
                MediaclassUploader.toggleVideoUrlForm(uploadContainer);
                return;
            }

            if (uploadContainer.find('.fileupload-container').length < 1) {
                uploadContainer.html(MediaclassUploader.template().html())
                    .attr('data-description', instantiator.data('description'));

                // Add dimension hint if requirements exist
                const requiredWidth = instantiator.data('required-width');
                const requiredHeight = instantiator.data('required-height');

                if (requiredWidth && requiredHeight) {
                    const fileuploadBar = uploadContainer.find('.fileupload-buttonbar');
                    const dimensionTemplate = MediaclassUploader.i18nText(instantiator, 'dimension_requirements');
                    const dimensionText = MediaclassUploader.interpolate(dimensionTemplate, {width: requiredWidth, height: requiredHeight});
                    const dimensionHint = `<div class="dimension-requirements text-center mb-3">
            <i class="bi bi-info-circle"></i>
            <strong>${dimensionText}</strong>
          </div>`;
                    fileuploadBar.prepend(dimensionHint);
                }

                MediaclassUploader.initFileupload(uploadContainer);
                MediaclassUploader.uploaderOptions(uploadContainer);
            } else {
                uploadContainer.html('');
            }
        });

        // Immediately disable uploader buttons where limit is already reached
        $('.mediaclass-uploadable').each(function () {
            const $this = $(this);
            if (MediaclassUploader.isLimitReached($this)) {
                $this.find('span.mediaclass-uploader').addClass('disabled');
            }
        });
    },

    toggleVideoUrlForm(uploadContainer) {
        const uploadable = this.uploadable(uploadContainer);

        if (uploadContainer.find('.mediaclass-video-url-form').length > 0) {
            uploadContainer.html('');
            return;
        }

        uploadContainer.html(this.videoUrlForm(uploadable));
        this.positions(uploadable);
    },

    videoUrlForm(uploadable) {
        const videoUrlLabel = this.i18nText(uploadable, 'video_url_label', 'Video URL');
        const videoUrlPlaceholder = this.i18nText(uploadable, 'video_url_placeholder', 'https://...');
        const addLabel = this.i18nText(uploadable, 'add', 'Add');
        const cancelLabel = this.i18nText(uploadable, 'cancel', 'Cancel');
        const descriptionLabel = this.i18nText(uploadable, 'description_label', 'Description');
        const positionsLabel = this.i18nText(uploadable, 'positions_label', 'Positions');
        const hideDescription = Number(uploadable.attr('data-has-description')) !== 1;
        const showPositions = uploadable.data('positions') === 1;

        let positions = '';
        if (showPositions) {
            positions = `
                <div class="col-12 positions text-center ps-2">
                    <b>${positionsLabel}</b>
                    <div class="choices pt-2">
                        <i class="bi bi-arrow-left-square-fill active" data-position="left"></i>
                        <i class="bi bi-arrow-up-square-fill" data-position="up"></i>
                        <i class="bi bi-arrow-down-square-fill" data-position="down"></i>
                        <i class="bi bi-arrow-right-square-fill" data-position="right"></i>
                        <input type="hidden" name="position" value="left">
                    </div>
                </div>`;
        }

        const descriptions = this.mediaLocales(uploadable).map((locale) => `
            <div class="col-lg-6 col-12 description ${hideDescription ? 'd-none' : ''}">
                <label class="form-label">${descriptionLabel} (${locale})</label>
                <textarea name="description[${locale}]" class="form-control description" rows="3"></textarea>
            </div>`).join('');

        return `
            <div class="mediaclass-video-url-form">
                <div class="mb-3">
                    <label class="form-label">${videoUrlLabel}</label>
                    <input type="url" name="url" class="form-control" placeholder="${videoUrlPlaceholder}">
                </div>
                <div class="row params mt-3">
                    ${positions}
                    ${descriptions}
                </div>
                <button type="button" class="btn btn-sm btn-warning mediaclass-video-url-submit">${addLabel}</button>
                <button type="button" class="btn btn-sm btn-secondary mediaclass-video-url-cancel ms-2">${cancelLabel}</button>
            </div>`;
    },

    submitVideoUrl(button) {
        const form = button.closest('.mediaclass-video-url-form');
        const uploadable = this.uploadable(form);
        const urlInput = form.find('input[name="url"]').first();
        const url = String(urlInput.val() || '').trim();

        MediaclassUploader.messages(uploadable).html('');

        if (!this.isValidUrl(url)) {
            const invalidUrl = this.i18nText(uploadable, 'invalid_url', 'The video URL is invalid');
            notificator(200, {danger: [invalidUrl]}, this.messages(uploadable), false, {isDismissable: true});
            urlInput.addClass('is-invalid');
            return;
        }

        urlInput.removeClass('is-invalid');
        this.setVeil(form);

        const formData = [
            {name: '_token', value: this.csrfToken()},
            {name: 'action', value: 'uploadUrl'},
            {name: 'group', value: uploadable.data('group')},
            {name: 'subgroup', value: uploadable.data('subgroup')},
            {name: 'positions', value: uploadable.data('positions')},
            {name: 'model', value: uploadable.data('model')},
            {name: 'model_id', value: uploadable.data('model-id')},
            {name: 'mediaclass_temp_id', value: $('input[name="mediaclass_temp_id"]').first().val() ?? ''},
            {name: 'count_files', value: 1},
            {name: 'ghost', value: uploadable.data('ghost') || '0'},
            {name: 'url', value: url},
        ];

        uploadable
            .find(':input[name^="mediaclass_storable"]')
            .serializeArray()
            .forEach((field) => {
                formData.push({
                    name: field.name.replace(/^mediaclass_storable/, 'storables'),
                    value: (field.value ?? '').trim(),
                });
            });

        form.find('textarea, input[type="hidden"]').each(function () {
            formData.push({
                name: $(this).attr('name'),
                value: $(this).val()
            });
        });

        $.ajax({
            url: this.template().data('ajax'),
            type: 'POST',
            dataType: 'json',
            data: formData,
            success: (data) => {
                this.removeVeil(form);

                if (data.hasOwnProperty('errors') || data.hasOwnProperty('error')) {
                    const errorData = data.mfw_ajax_messages ?? data.messages;
                    notificator(200, errorData, this.messages(uploadable), false, {isDismissable: true});
                    return;
                }

                if (!data.uploaded) {
                    const uploadErrorTitle = this.i18nText(uploadable, 'upload_error_title');
                    notificator(uploadErrorTitle, 'danger', this.messages(uploadable));
                    return;
                }

                this.executeCallback(uploadable, data);
                this.appendUploadedMedia(uploadable, data, Number(uploadable.attr('data-has-description')) !== 1);
                this.uploadableContainer(uploadable).html('');
            },
            error: () => {
                this.removeVeil(form);
                const uploadErrorGeneric = this.i18nText(uploadable, 'upload_error_generic');
                notificator(200, {danger: [uploadErrorGeneric]}, this.messages(uploadable), false, {isDismissable: true});
            }
        });
    },

    uploaderOptions(uploadContainer) {
        const fileuploadContainer = this.fileupload(uploadContainer);
        const uploadable = this.uploadable(uploadContainer);
        const limit = Number(uploadable.data('limit'));
        const inputFileSize = uploadable.data('maxfilesize');
        const maxFileSize = this.calculateMaxFileSize(inputFileSize);
        const messagesUI = uploadable.find('.ui-messages');
        const acceptFileTypes = this.i18nText(uploadable, 'accept_file_types');

        // Get dimensions from data attributes
        const requiredWidth = uploadable.data('required-width');
        const requiredHeight = uploadable.data('required-height');

        const options = {
            previewMaxWidth: 220,
            previewMaxHeight: 220,
            acceptFileTypes: /(\.|\/)(jpe?g|png|svg|pdf)$/i,
            maxFileSize: maxFileSize,
            autoUpload: false,
            maxNumberOfFiles: limit > 0 ? limit : null,
            messages: {
                maxNumberOfFiles: `${messagesUI.find('.maxNumberOfFiles').first().text()} ${limit}`,
                acceptFileTypes: acceptFileTypes,
                maxFileSize: `${messagesUI.find('.maxFileSize').first().text()} ${inputFileSize || ((this.defaultFileSize / 1024 / 1024) + 'MB')}`,
            },
        };

        // Add dimension validation messages if requirements exist
        if (requiredWidth && requiredHeight) {
            const minWidthTemplate = this.i18nText(uploadable, 'min_image_width');
            const minHeightTemplate = this.i18nText(uploadable, 'min_image_height');
            const imageDimensionsTemplate = this.i18nText(uploadable, 'image_dimensions');
            options.messages.minImageWidth = this.interpolate(minWidthTemplate, {width: requiredWidth});
            options.messages.minImageHeight = this.interpolate(minHeightTemplate, {height: requiredHeight});
            options.messages.imageDimensions = this.interpolate(imageDimensionsTemplate, {width: requiredWidth, height: requiredHeight});
        }

        fileuploadContainer.fileupload('option', options);
    },

    positions(uploadable) {
        uploadable.find('.positions i').off().on('click', function () {
            const $this = $(this);
            const positionsContainer = $this.closest('.positions');

            positionsContainer.find('i').removeClass('active');
            $this.addClass('active');
            positionsContainer.find('input').val($this.data('position'));
        });
    },

    initFileupload(uploadContainer) {
        const fileuploadContainer = this.fileupload(uploadContainer);
        const uploadable = this.uploadable(fileuploadContainer);
        const hideDescription = Number(uploadable.attr('data-has-description')) !== 1;

        // Only destroy existing fileupload instance if it exists to prevent conflicts
        if (fileuploadContainer.data('blueimp-fileupload') || fileuploadContainer.data('fileupload')) {
            fileuploadContainer.fileupload('destroy');
        }

        // Your original event handler for fileuploadadd
        fileuploadContainer.off('fileuploadadd fileuploadsubmit');

        fileuploadContainer.on('fileuploadadd', function () {
            fileuploadContainer.find('.uploadables').removeClass('d-none');

            setTimeout(() => {
                if (uploadable.data('positions') !== 1) {
                    uploadable.find('.positions').addClass('d-none');
                }
                if (hideDescription) {
                    uploadable.find('.description').addClass('d-none');
                }
                MediaclassUploader.positions(uploadable);
            }, 1);
        }).fileupload({
            url: MediaclassUploader.template().data('ajax'),
            dataType: 'json',
            context: fileuploadContainer[0],
            sequentialUploads: true,
            type: 'POST',
            done: () => {
                MediaclassUploader.progress(uploadable).hide();
            },
            success: (data) => {
                MediaclassUploader.alerts(uploadable).html('');

                // Check for errors FIRST before doing anything else
                if (data.hasOwnProperty('errors') || data.hasOwnProperty('error')) {
                    const errorData = data.mfw_ajax_messages ?? data.messages;
                    notificator(200, errorData, MediaclassUploader.messages(uploadable), false, {isDismissable: true});

                    uploadable.find('.files .template-upload').fadeOut(function () {
                        $(this).remove();
                        if (uploadable.find('.files .template-upload').length === 0) {
                            uploadable.find('.uploadables').addClass('d-none');
                        }
                    });
                    return;
                }

                if (!data.uploaded) {
                    console.error('No uploaded data in response', data);
                    const uploadErrorTitle = MediaclassUploader.i18nText(uploadable, 'upload_error_title');
                    notificator(uploadErrorTitle, 'danger', MediaclassUploader.messages(uploadable));
                    return;
                }

                // Execute callback if defined (before UI updates)
                MediaclassUploader.executeCallback(uploadable, data);

                // Hide the upload queue first, then add the new content after it's hidden
                uploadable.find('.files').fadeOut(300, function () {
                    $(this).html('').show();
                    MediaclassUploader.appendUploadedMedia(uploadable, data, hideDescription);
                });
            },
            error: (xhr, ajaxOptions, thrownError) => {
                console.error('Upload error:', xhr, thrownError);

                // Check if it's a dimension error from the response
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    // Format the error for notificator
                    const errorData = {
                        danger: [xhr.responseJSON.errors]
                    };
                    notificator(200, errorData, MediaclassUploader.messages(uploadable), false, {isDismissable: true});
                } else {
                    // Generic error message
                    const uploadErrorGeneric = MediaclassUploader.i18nText(uploadable, 'upload_error_generic');
                    const errorData = {
                        danger: [uploadErrorGeneric]
                    };
                    notificator(200, errorData, MediaclassUploader.messages(uploadable), false, {isDismissable: true});
                }

                // Clean up the upload UI
                uploadable.find('.files .template-upload').fadeOut(function () {
                    $(this).remove();

                    // If no more files in queue, hide the uploadables section
                    if (uploadable.find('.files .template-upload').length === 0) {
                        uploadable.find('.uploadables').addClass('d-none');
                    }
                });
            },
            start: () => {
                MediaclassUploader.messages(uploadable).html('');
                MediaclassUploader.progress(uploadable).show();
            },
        });

        fileuploadContainer.on('fileuploadsubmit', (e, data) => {
            MediaclassUploader.messages(uploadable).html('');

            // Count valid files
            let validFiles = 0;
            uploadable.find('.files > div').each(function () {
                if ($(this).find('.error').first().text().length < 1) {
                    validFiles += 1;
                }
            });

            // Get cropable data
            let cropableData = uploadable.data('cropable');

            // If it's already a string (JSON), use it as is
            // If it's an object, stringify it
            if (typeof cropableData === 'object' && cropableData !== null) {
                cropableData = JSON.stringify(cropableData);
            }

            // Set form data
            data.formData = [
                {name: '_token', value: MediaclassUploader.csrfToken()},
                {name: 'action', value: 'upload'},
                {name: 'group', value: uploadable.data('group')},
                {name: 'subgroup', value: uploadable.data('subgroup')},
                {name: 'positions', value: uploadable.data('positions')},
                {name: 'model', value: uploadable.data('model')},
                {name: 'model_id', value: uploadable.data('model-id')},
                {name: 'mediaclass_temp_id', value: $('input[name="mediaclass_temp_id"]').first().val() ?? ''},
                {name: 'count_files', value: validFiles},
                {name: 'ghost', value: uploadable.data('ghost') || '0'},
                {name: 'cropable', value: cropableData || ''},
            ];

            uploadable
                .find(':input[name^="mediaclass_storable"]')
                .serializeArray()
                .forEach((field) => {
                    data.formData.push({
                        name: field.name.replace(/^mediaclass_storable/, 'storables'),
                        value: (field.value ?? '').trim(),
                    });
                });

            // Add form fields
            data.context.find('textarea, input').each(function () {
                data.formData.push({
                    name: $(this).attr('name'),
                    value: $(this).val()
                });
            });
        });
    },

    appendUploadedMedia(uploadable, data, hideDescription) {
        const html = this.buildUploadedFileHTML(data, hideDescription, uploadable);

        let lightGalleryContainer = uploadable.find('.lightgallery-container');
        if (lightGalleryContainer.length === 0) {
            uploadable.find('.uploaded').wrapInner(`<div id="lightgallery-${uploadable.data('group')}-${uploadable.data('model-id')}" class="lightgallery-container"></div>`);
            lightGalleryContainer = uploadable.find('.lightgallery-container');
        }

        lightGalleryContainer.append(html);
        this.unlinkable();

        setTimeout(() => {
            const imageItems = lightGalleryContainer.find('.lightgallery-item');

            if (imageItems.length > 0) {
                const lgInstance = lightGalleryContainer.data('lightGallery');
                if (lgInstance) {
                    lgInstance.destroy();
                }

                lightGallery(lightGalleryContainer[0], {
                    selector: '.lightgallery-item',
                    speed: 500,
                    download: true,
                    counter: true,
                    zoom: true,
                    thumbnail: imageItems.length > 1,
                    plugins: [lgZoom, lgThumbnail],
                    mobileSettings: {
                        controls: true,
                        showCloseIcon: true,
                        download: true
                    }
                });
            }
        }, 100);

        if (this.isLimitReached(uploadable)) {
            uploadable.find('span.mediaclass-uploader').addClass('disabled');
            this.uploadableContainer(uploadable).html('');
        } else if (uploadable.find('.uploaded div.mediaclass.unlinkable').length === Number(data.count_files)) {
            this.uploadableContainer(uploadable).html('');
        }

        this.modalCrop();
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

        // For images, we need to get the full size URL for LightGallery
        const fullSizeUrl = filetype === 'image' ? (data.urls && data.urls.xl ? data.urls.xl : link) : link;

        let html = `
<div class="mediaclass unlinkable uploaded-image my-2" data-id="${uploaded.id}" id="mediaclass-${uploaded.id}">
    <span class="unlink"><i class="bi bi-x-circle-fill"></i></span>
    <div class="row m-0">
        <div class="col-xl-3 pe-xl-4 col-12 impImg position-relative preview ${filetype}">`;

        if (filetype === 'image') {
            // For images: make the entire preview area clickable with LightGallery
            html += `
            <a href="${fullSizeUrl}"
               class="lightgallery-item d-block w-100 h-100"
               data-sub-html="<h4>${uploaded.original_filename}</h4><p>${uploaded.description ? (uploaded.description[document.documentElement.lang] || '') : ''}</p>"
               style="background-image: url(${preview}); background-size: contain; background-repeat: no-repeat; background-position: center;">
                <div class="actions">
                    <i class="bi bi-zoom-in"></i>
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
                        <span class="rounded-1 py-1 px-2 text-bg-secondary">${uploaded.original_filename}</span>
                        <span class="rounded-1 py-1 px-2 bg-light-subtle text-dark opacity-75">
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
        for (const position of this.positions_tags) {
            const isActive = uploaded.position === position ? ' active' : '';
            html += `<i class="bi bi-arrow-${position}-square-fill${isActive}" data-position="${position}"></i>`;
        }

        html += `
                        <input type="hidden" name="mediaclass[${uploaded.id}][position]" value="${uploaded.position || 'left'}">
                    </div>
                </div>`;

        // Add descriptions
        const descriptions = uploaded.description || {};
        for (const [key, value] of Object.entries(descriptions)) {
            html += `
                <div class="col-lg-6 col-12 description ${hideDescription ? ' d-none' : ''}">
                    <div class="mt-2">
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
        const ajaxUrl = this.template().data('ajax');

        // Clean up any existing event handlers
        $modal.off('shown.bs.modal');
        $modal.off('hidden.bs.modal');
        $(document).off('ajaxSuccess.mediaclassCrop');

        // Handle crop button clicks
        $(document).on('click', '.crop-actions-bar .crop', function (e) {
            e.preventDefault();
            const $btn = $(this);
            const isView = $btn.hasClass('cropped');

            // Clear previous modal content
            $modal.find('.modal-body').empty();

            if (isView) {
                // Clone the template content
                const template = $('#mediaclass-crop-view-template').html();
                $modal.find('.modal-body').html(template);

                // Set timeout to ensure DOM is ready
                setTimeout(() => {
                    // Populate modal content
                    const cropLabel = $btn.data('crop-label') || $btn.data('crop-key');

                    $modal.find('.crop-key-title').text(cropLabel);
                    $modal.find('.crop-key-label').text($btn.data('crop-key'));
                    $modal.find('.crop-dimensions-text, .crop-dimensions-label')
                        .text($btn.data('crop-w') + ' x ' + $btn.data('crop-h'));
                    $modal.find('.crop-preview-image')
                        .attr('src', $btn.data('preview-url'));

                    // Get filename from parent element
                    const filename = $btn.closest('.mediaclass').find('.name span:first').text();
                    $modal.find('.crop-filename').text($btn.data('crop-key') + '_' + filename);

                    // Set form values
                    const $form = $modal.find('#mediaclass-delete-crop-form');
                    $form.attr('data-ajax', ajaxUrl);
                    $form.attr('data-media-id', $btn.data('media-id'));
                    $form.attr('data-crop-key', $btn.data('crop-key'));

                    // Resize container to image width (cap at modal width and 1140px)
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
                // Load crop editor
                $modal.find('.modal-body').load($btn.attr('href'), function () {
                    $modal.modal('show');
                });
            }
        });

        // Handle delete button click
        $modal.on('click', '#mediaclass-delete-crop-btn', function () {
            let c = MediaclassUploader.deleteCropForm();
            mfwAjax('action=deleteCrop&media_id=' + c.attr('data-media-id') + '&crop_key=' + c.attr('data-crop-key'), $(c));
        });

        // Handle AJAX success
        $(document).on('ajaxSuccess.mediaclassCrop', function (_e, xhr) {
            const ct = (xhr.getResponseHeader('Content-Type') || '').toLowerCase();
            if (!ct.includes('application/json')) return;

            try {
                const res = JSON.parse(xhr.responseText);
                if (res.action === 'delete_crop') {
                    MediaclassUploader.deletedCrop(res);
                }
            } catch (e) {
                console.error('Error parsing JSON response', e);
            }
        });

        // Clean up on modal close
        $modal.on('hidden.bs.modal', function () {
            $(this).find('.modal-body').empty();
            $(this).find('.mediaclass-crop-dialog').css('width', '');
        });
    },

    hideModal() {
        setTimeout(() => {
            const $modalCrop = $('#mediaclass-crop');
            $modalCrop.modal('hide');

            $('body').on('hidden.bs.modal', '.modal', function () {
                $modalCrop.find('.modal-body').html('');
            });
        }, 1500);
    },

    cropped: function (result) {
        // Update the UI after cropping
        if (result.uploaded && result.uploaded.id) {
            var $mediaElement = $('#mediaclass-' + result.uploaded.id);

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
                // Replace the entire crop actions bar with the updated one
                $mediaElement.find('.crop-actions-bar').replaceWith(result.cropable_links);

                // Re-initialize the crop actions for the new buttons
                this.initCropActions();
            }

            // Update the preview image if new URL provided
            if (result.urls && result.urls.xl) {
                $mediaElement.find('.preview').css('background-image', `url(${result.urls.xl})`);
                $mediaElement.find('.zoom').attr('href', result.urls.xl);
            }
        }

        MediaclassUploader.hideModal();
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

        MediaclassUploader.hideModal();
    },

    initCropActions: function () {
        // Modal already handles the loading via href, just ensure it's properly initialized
        var $modalCrop = $('#mediaclass-crop');

        // Ensure modal content is cleared when hidden
        $modalCrop.off('hidden.bs.modal').on('hidden.bs.modal', function () {
            $(this).find('.modal-body').empty();
        });
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
                const $actions = $previewDiv.find('.actions').clone();

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

                // Get the description for lightgallery
                const $mediaElement = $preview.closest('.mediaclass');
                const filename = $mediaElement.find('.name span:first').text();
                const description = $mediaElement.find('textarea.description').first().val() || '';

                // Clone the actions div
                const $actions = $previewDiv.find('.actions').clone();

                // Create new lightgallery link
                const $newLink = $('<a>')
                    .attr('href', href)
                    .addClass('lightgallery-item d-block w-100 h-100')
                    .attr('data-sub-html', `<h4>${filename}</h4><p>${description}</p>`)
                    .attr('style', bgStyle);

                // Append the actions
                $newLink.append($actions);

                // Replace the div with the new link
                $previewDiv.replaceWith($newLink);
            }
        });

        // Re-initialize LightGallery only for containers with images
        $('.lightgallery-container').each(function () {
            const $container = $(this);
            const imageItems = $container.find('.lightgallery-item');

            // Destroy existing instance
            const lgInstance = $container.data('lightGallery');
            if (lgInstance) {
                lgInstance.destroy();
            }

            // Only init if there are image items
            if (imageItems.length > 0) {
                lightGallery(this, {
                    selector: '.lightgallery-item',
                    speed: 500,
                    download: true,
                    counter: true,
                    zoom: true,
                    thumbnail: imageItems.length > 1,
                    plugins: [lgZoom, lgThumbnail],
                    mobileSettings: {
                        controls: true,
                        showCloseIcon: true,
                        download: true
                    }
                });
            }
        });
    },

    bindMediaTypeOptions() {
        $(document)
            .off('change.mediaclassMediaType', '.mediaclass-media-type')
            .on('change.mediaclassMediaType', '.mediaclass-media-type', function () {
                const uploadable = $(this).closest('.mediaclass-uploadable');

                MediaclassUploader.uploadableContainer(uploadable).html('');
            })
            .off('click.mediaclassVideoUrlSubmit', '.mediaclass-video-url-submit')
            .on('click.mediaclassVideoUrlSubmit', '.mediaclass-video-url-submit', function () {
                MediaclassUploader.submitVideoUrl($(this));
            })
            .off('click.mediaclassVideoUrlCancel', '.mediaclass-video-url-cancel')
            .on('click.mediaclassVideoUrlCancel', '.mediaclass-video-url-cancel', function () {
                MediaclassUploader.uploadableContainer($(this)).html('');
            });
    },

    init() {
        // Fix modal locations first
        this.fixModalLocation();

        // Also fix on tab changes
        $('a[data-toggle="tab"], button[data-bs-toggle="tab"]').on('shown.bs.tab', () => {
            this.fixModalLocation();
        });

        this.fixExistingFileLinks();

        // Initialize positions for all uploadable elements
        $('.mediaclass-uploadable').each(function () {
            MediaclassUploader.positions($(this));
        });

        // Setup event handlers
        this.uploaderCall();
        this.unlinkable();
        this.bindMediaTypeOptions();
        this.modalCrop();
        this.initCropActions();
    },
};

// Initialize the module
MediaclassUploader.init();

// Callbacks
function mediaclassDeletedCrop(result) {
    MediaclassUploader.deletedCrop(result);
}

function mediaclassCropped(result) {
    MediaclassUploader.cropped(result);
}
