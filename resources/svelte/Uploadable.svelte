<script lang="ts">
  import { onDestroy, onMount } from 'svelte';

  type MediaTypeMap = Record<string, string>;
  type I18nMap = Record<string, string>;
  type UploadStatus = 'queued' | 'uploading' | 'uploaded' | 'error';

  type Config = {
    ajaxUrl: string;
    buttonIcon: string;
    buttonLabel: string;
    cropable: string;
    dimensionsInline: string;
    enforceDimensions: boolean;
    ghost: string;
    group: string;
    hasDescription: boolean;
    i18n: I18nMap;
    limit: number;
    maxFileSizeBytes: number;
    maxFileSizeLabel: string;
    mediaLocales: string[];
    mediaTypes: MediaTypeMap;
    model: string;
    modelId: string;
    positions: boolean;
    requiredHeight: string;
    requiredWidth: string;
    subgroup: string;
  };

  type QueueItem = {
    descriptions: Record<string, string>;
    error: string;
    file: File;
    id: string;
    position: string;
    previewUrl: string;
    progress: number;
    status: UploadStatus;
  };

  type VideoInput = {
    descriptions: Record<string, string>;
    embedHeight: string;
    embedHeightMode: 'auto' | 'pixels';
    embedWidth: string;
    embedWidthMode: 'pixels' | 'full';
    position: string;
    url: string;
  };

  let { host }: { host: HTMLElement } = $props();

  const positions = ['left', 'up', 'down', 'right'];
  const fileInputId = `mediaclass-svelte-files-${Math.random().toString(36).slice(2)}`;

  let uploadable: HTMLElement | undefined;
  let observer: MutationObserver | null = null;
  let queue = $state<QueueItem[]>([]);
  let config = $state<Config>(defaultConfig());
  let selectedType = $state('image');
  let showFilePanel = $state(false);
  let showVideoPanel = $state(false);
  let limitReached = $state(false);
  let hasStoredVideos = $state(false);
  let videoUploading = $state(false);
  let videoError = $state('');
  let video = $state<VideoInput>(defaultVideoInput([]));

  let activeTypes = $derived(Object.keys(config.mediaTypes));
  let hasMultipleTypes = $derived(activeTypes.length > 1);
  let queuedItems = $derived(queue.filter((item) => item.status === 'queued' || item.status === 'error'));
  let hasQueue = $derived(queue.length > 0);
  let isUploading = $derived(queue.some((item) => item.status === 'uploading') || videoUploading);
  let canUpload = $derived(queuedItems.length > 0 && !isUploading && !limitReached);
  let detailsButtonVisible = $derived(config.hasDescription || hasStoredVideos);

  onMount(() => {
    uploadable = host.closest('.mediaclass-uploadable') as HTMLElement | undefined;

    if (!uploadable) {
      return () => {};
    }

    config = readConfig(uploadable, host);
    selectedType = Object.keys(config.mediaTypes)[0] ?? 'image';
    video = defaultVideoInput(config.mediaLocales);
    syncStoredMediaState();

    const uploaded = uploadable.querySelector('.uploaded');
    if (uploaded && typeof MutationObserver !== 'undefined') {
      observer = new MutationObserver(() => {
        syncStoredMediaState();
        setVideoPanelOpen(showVideoPanel);
      });
      observer.observe(uploaded, {
        childList: true,
        subtree: true
      });
    }

    return () => {
      observer?.disconnect();
      observer = null;
    };
  });

  onDestroy(() => {
    observer?.disconnect();
    queue.forEach((item) => URL.revokeObjectURL(item.previewUrl));
  });

  function defaultConfig(): Config {
    return {
      ajaxUrl: '',
      buttonIcon: 'bi bi-plus-circle',
      buttonLabel: '',
      cropable: '',
      dimensionsInline: '',
      enforceDimensions: false,
      ghost: '0',
      group: '',
      hasDescription: false,
      i18n: {},
      limit: 0,
      maxFileSizeBytes: 16 * 1024 * 1024,
      maxFileSizeLabel: '16MB',
      mediaLocales: [locale()],
      mediaTypes: { image: 'Image' },
      model: '',
      modelId: '',
      positions: false,
      requiredHeight: '',
      requiredWidth: '',
      subgroup: '',
    };
  }

  function defaultVideoInput(locales: string[]): VideoInput {
    return {
      descriptions: Object.fromEntries((locales.length > 0 ? locales : [locale()]).map((value) => [value, ''])),
      embedHeight: '315',
      embedHeightMode: 'auto',
      embedWidth: '560',
      embedWidthMode: 'pixels',
      position: 'left',
      url: ''
    };
  }

  function readConfig(element: HTMLElement, mount: HTMLElement): Config {
    const mediaTypes = parseJson<MediaTypeMap>(element.dataset.mediaTypes, { image: 'Image' });
    const locales = parseJson<string[]>(element.dataset.mediaLocales, [locale()]);

    return {
      ajaxUrl: element.dataset.ajax ?? '',
      buttonIcon: mount.dataset.icon ?? 'bi bi-plus-circle',
      buttonLabel: mount.dataset.label ?? '',
      cropable: element.dataset.cropable ?? '',
      dimensionsInline: mount.dataset.dimensionsInline ?? '',
      enforceDimensions: element.dataset.enforceDimensions === '1',
      ghost: element.dataset.ghost ?? '0',
      group: element.dataset.group ?? '',
      hasDescription: element.dataset.hasDescription === '1',
      i18n: parseJson<I18nMap>(element.dataset.i18n, {}),
      limit: Number(element.dataset.limit ?? '0') || 0,
      maxFileSizeBytes: parseMaxFileSize(element.dataset.maxfilesize),
      maxFileSizeLabel: element.dataset.maxfilesize || '16MB',
      mediaLocales: Array.isArray(locales) && locales.length > 0 ? locales : [locale()],
      mediaTypes: Object.keys(mediaTypes).length > 0 ? mediaTypes : { image: 'Image' },
      model: element.dataset.model ?? '',
      modelId: element.dataset.modelId ?? '',
      positions: element.dataset.positions === '1',
      requiredHeight: element.dataset.requiredHeight ?? '',
      requiredWidth: element.dataset.requiredWidth ?? '',
      subgroup: element.dataset.subgroup ?? ''
    };
  }

  function parseJson<T>(value: string | undefined, fallback: T): T {
    if (!value) {
      return fallback;
    }

    try {
      return JSON.parse(value) as T;
    } catch {
      return fallback;
    }
  }

  function parseMaxFileSize(value: string | undefined): number {
    if (!value) {
      return 16 * 1024 * 1024;
    }

    const amount = Number(value.replace(/\D+/g, ''));

    if (!Number.isFinite(amount) || amount <= 0) {
      return 16 * 1024 * 1024;
    }

    if (value.toUpperCase().includes('KB')) {
      return amount * 1024;
    }

    if (value.toUpperCase().includes('MB')) {
      return amount * 1024 * 1024;
    }

    return amount;
  }

  function text(key: string, fallback = ''): string {
    if (uploadable && window.MediaclassManager?.i18nText && typeof window.jQuery !== 'undefined') {
      return window.MediaclassManager.i18nText(window.jQuery(uploadable), key, fallback);
    }

    const value = config.i18n[key];

    return value === undefined || value === null || value === '' ? fallback : String(value);
  }

  function locale(): string {
    return document.documentElement.lang || navigator.language || 'en';
  }

  function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
  }

  function tempId(): string {
    return document.querySelector<HTMLInputElement>('input[name="mediaclass_temp_id"]')?.value ?? '';
  }

  function currentCount(): number {
    return uploadable?.querySelectorAll('.uploaded div.mediaclass.unlinkable').length ?? 0;
  }

  function setVideoPanelOpen(open: boolean): void {
    showVideoPanel = open;
    uploadable?.querySelectorAll<HTMLElement>('[data-mediaclass-empty-state]').forEach((emptyState) => {
      emptyState.hidden = open;
    });
  }

  function syncStoredMediaState(): void {
    hasStoredVideos = Boolean(uploadable?.querySelector('.uploaded .preview.video'));
    limitReached = config.limit > 0 && currentCount() >= config.limit;

    if (!limitReached) {
      return;
    }

    showFilePanel = false;
    setVideoPanelOpen(false);
  }

  function remainingSlots(): number {
    if (config.limit <= 0) {
      return Number.POSITIVE_INFINITY;
    }

    return Math.max(config.limit - currentCount(), 0);
  }

  function selectType(type: string): void {
    selectedType = type;
    syncStoredMediaState();

    if (limitReached) {
      showLimitReached();

      return;
    }

    showFilePanel = false;
    setVideoPanelOpen(type === 'video');
  }

  function toggleActivePanel(): void {
    syncStoredMediaState();

    if (limitReached) {
      showLimitReached();

      return;
    }

    if (selectedType === 'video') {
      setVideoPanelOpen(!showVideoPanel);
      showFilePanel = false;

      return;
    }

    showFilePanel = !showFilePanel;
    setVideoPanelOpen(false);
  }

  function showLimitReached(): void {
    const message = interpolate(text('limit_reached', 'Limit of :count file(s) reached'), {
      count: String(config.limit)
    });

    showLocalAlert(message, 'warning');
  }

  function interpolate(template: string, params: Record<string, string>): string {
    return Object.entries(params).reduce(
      (output, [key, value]) => output.replace(new RegExp(`:${key}\\b`, 'g'), value),
      template
    );
  }

  function showLocalAlert(message: string, type: 'danger' | 'warning' | 'info' = 'danger'): void {
    const alerts = uploadable?.querySelector('.mediaclass-alerts');

    if (alerts) {
      alerts.innerHTML = `<div class="alert alert-${type}">${escapeHtml(message)}</div>`;
    }
  }

  function notifyError(message: string): void {
    if (typeof window.notificator === 'function' && typeof window.jQuery !== 'undefined' && uploadable) {
      window.notificator(200, { danger: [message] }, window.jQuery(uploadable).find('.mediaclass-alerts'), false, {
        isDismissable: true
      });

      return;
    }

    showLocalAlert(message, 'danger');
  }

  function escapeHtml(value: string): string {
    const element = document.createElement('div');
    element.textContent = value;

    return element.innerHTML;
  }

  function inputId(...parts: Array<number | string>): string {
    return ['mediaclass-svelte', ...parts]
      .join('-')
      .replace(/[^A-Za-z0-9_-]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  function openFileSelector(): void {
    document.getElementById(fileInputId)?.click();
  }

  function handleFiles(event: Event): void {
    const input = event.currentTarget as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    const slots = remainingSlots();
    const accepted = Number.isFinite(slots) ? files.slice(0, slots) : files;

    if (files.length > accepted.length) {
      showLimitReached();
    }

    const items = accepted.map((file) => createQueueItem(file));
    queue = [...queue, ...items].slice(0, Number.isFinite(slots) ? slots : undefined);
    input.value = '';
  }

  function createQueueItem(file: File): QueueItem {
    const error = validateFile(file);

    return {
      descriptions: Object.fromEntries(config.mediaLocales.map((value) => [value, ''])),
      error,
      file,
      id: `${file.name}-${file.lastModified}-${crypto.randomUUID?.() ?? Math.random().toString(36).slice(2)}`,
      position: 'left',
      previewUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : '',
      progress: 0,
      status: error ? 'error' : 'queued'
    };
  }

  function validateFile(file: File): string {
    const allowed = ['image/jpeg', 'image/png', 'image/svg+xml', 'application/pdf'];

    if (!allowed.includes(file.type)) {
      return text('accept_file_types', 'File type not allowed');
    }

    if (file.size > config.maxFileSizeBytes) {
      return `${text('maxFileSize', 'File is too large. Maximum size: ')} ${config.maxFileSizeLabel}`;
    }

    return '';
  }

  function removeItem(item: QueueItem): void {
    URL.revokeObjectURL(item.previewUrl);
    queue = queue.filter((candidate) => candidate.id !== item.id);
  }

  function clearQueue(): void {
    queue.forEach((item) => URL.revokeObjectURL(item.previewUrl));
    queue = [];
  }

  async function uploadQueue(): Promise<void> {
    for (const item of queuedItems) {
      if (item.error) {
        continue;
      }

      await uploadFile(item);
      syncStoredMediaState();

      if (limitReached) {
        break;
      }
    }

    queue = queue.filter((item) => item.status !== 'uploaded');
  }

  function uploadFile(item: QueueItem): Promise<void> {
    item.status = 'uploading';
    item.progress = 0;

    const formData = baseFormData();
    formData.set('action', 'upload');
    formData.set('count_files', String(queuedItems.filter((candidate) => !candidate.error).length));
    formData.set('cropable', config.cropable);
    formData.set('maxfilesize', config.maxFileSizeLabel);
    formData.append('files[]', item.file);
    formData.set('position', item.position);

    Object.entries(item.descriptions).forEach(([key, value]) => {
      formData.set(`description[${key}]`, value);
    });

    return send(formData, (progress) => {
      item.progress = progress;
    }).then((data) => {
      if (hasResponseError(data)) {
        item.status = 'error';
        item.error = responseErrorMessage(data);
        notifyError(item.error);

        return;
      }

      if (!data.uploaded) {
        item.status = 'error';
        item.error = text('upload_error_title', 'Upload error');
        notifyError(item.error);

        return;
      }

      finishSuccessfulUpload(data);
      item.status = 'uploaded';
      URL.revokeObjectURL(item.previewUrl);
    }).catch(() => {
      item.status = 'error';
      item.error = text('upload_error_generic', 'An error occurred while uploading your file');
      notifyError(item.error);
    });
  }

  function submitVideo(): void {
    const parsedUrl = parseUrl(video.url);

    videoError = '';

    if (!parsedUrl) {
      videoError = text('invalid_url', 'The video URL is invalid');
      notifyError(videoError);

      return;
    }

    videoUploading = true;

    const formData = baseFormData();
    formData.set('action', 'uploadUrl');
    formData.set('count_files', '1');
    formData.set('url', parsedUrl.toString());
    formData.set('position', video.position);
    formData.set('embed_width_mode', video.embedWidthMode);
    formData.set('embed_width', video.embedWidth);
    formData.set('embed_height_mode', video.embedHeightMode);
    formData.set('embed_height', video.embedHeightMode === 'auto' ? 'auto' : video.embedHeight);

    Object.entries(video.descriptions).forEach(([key, value]) => {
      formData.set(`description[${key}]`, value);
    });

    send(formData)
      .then((data) => {
        if (hasResponseError(data)) {
          videoError = responseErrorMessage(data);
          notifyError(videoError);

          return;
        }

        if (!data.uploaded) {
          videoError = text('upload_error_title', 'Upload error');
          notifyError(videoError);

          return;
        }

        finishSuccessfulUpload(data);
        video = defaultVideoInput(config.mediaLocales);
        setVideoPanelOpen(false);
      })
      .catch(() => {
        videoError = text('upload_error_generic', 'An error occurred while uploading your file');
        notifyError(videoError);
      })
      .finally(() => {
        videoUploading = false;
      });
  }

  function parseUrl(value: string): URL | null {
    try {
      const url = new URL(value);

      return ['http:', 'https:'].includes(url.protocol) ? url : null;
    } catch {
      return null;
    }
  }

  function baseFormData(): FormData {
    const formData = new FormData();
    formData.set('_token', csrfToken());
    formData.set('group', config.group);
    formData.set('subgroup', config.subgroup);
    formData.set('positions', config.positions ? '1' : '0');
    formData.set('model', config.model);
    formData.set('model_id', config.modelId);
    formData.set('mediaclass_temp_id', tempId());
    formData.set('ghost', config.ghost);

    uploadable?.querySelectorAll<HTMLInputElement>('input[name^="mediaclass_storable["]').forEach((input) => {
      formData.set(input.name.replace(/^mediaclass_storable/, 'storables'), input.value.trim());
    });

    return formData;
  }

  function send(formData: FormData, onProgress?: (progress: number) => void): Promise<UploadResponse> {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();

      xhr.open('POST', config.ajaxUrl);
      xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.upload.onprogress = (event) => {
        if (event.lengthComputable && onProgress) {
          onProgress(Math.round((event.loaded / event.total) * 100));
        }
      };

      xhr.onload = () => {
        const data = parseJson<UploadResponse>(xhr.responseText, {});

        if (xhr.status >= 200 && xhr.status < 300) {
          resolve(data);

          return;
        }

        reject(data);
      };

      xhr.onerror = () => reject(new Error('Upload failed'));
      xhr.send(formData);
    });
  }

  function hasResponseError(data: UploadResponse): boolean {
    return Object.prototype.hasOwnProperty.call(data, 'errors') || Object.prototype.hasOwnProperty.call(data, 'error');
  }

  function responseErrorMessage(data: UploadResponse): string {
    const messages = data.mfw_ajax_messages ?? data.messages ?? data.errors ?? data.error;

    if (typeof messages === 'string') {
      return messages;
    }

    if (Array.isArray(messages)) {
      return messages.join(' ');
    }

    if (messages && typeof messages === 'object') {
      return Object.values(messages as Record<string, unknown>).flat().join(' ');
    }

    return text('upload_error_generic', 'An error occurred while uploading your file');
  }

  function finishSuccessfulUpload(data: UploadResponse): void {
    if (!uploadable || typeof window.jQuery === 'undefined') {
      return;
    }

    const manager = window.MediaclassManager;
    const wrapped = window.jQuery(uploadable);

    manager?.printResponseMessages(wrapped, data);
    manager?.executeCallback(wrapped, data);
    manager?.appendUploadedMedia(wrapped, data, !config.hasDescription);
    syncStoredMediaState();
  }
</script>

<div class="mediaclass-svelte-panel">
  <div class="mediaclass-svelte-toolbar">
    <button
      type="button"
      class="mediaclass-svelte-open"
      disabled={limitReached}
      onclick={toggleActivePanel}
    >
      <i class={config.buttonIcon || 'bi bi-plus-circle'}></i>
      <span>{config.buttonLabel || config.mediaTypes[selectedType] || text('select', 'Select')}</span>
    </button>

    {#if config.dimensionsInline}
      <span class="mediaclass-svelte-inline-dimensions">{config.dimensionsInline}</span>
    {/if}

    {#if hasMultipleTypes}
      <div class="mediaclass-svelte-types" role="radiogroup" aria-label={text('media_type', 'Media type')}>
        {#each activeTypes as type (type)}
          <button
            type="button"
            class:active={selectedType === type}
            aria-pressed={selectedType === type}
            onclick={() => selectType(type)}
          >
            {config.mediaTypes[type]}
          </button>
        {/each}
      </div>
    {/if}

    {#if detailsButtonVisible}
      <button type="button" class="btn btn-sm btn-secondary mediaclass-save-descriptions ms-auto">
        <i class="bi bi-save"></i>
        {text('save_media_details', 'Save media details')}
        <span class="ajax-spinner spinner-border spinner-border-sm ms-1" role="status" aria-hidden="true"></span>
      </button>
    {/if}
  </div>

  {#if config.requiredWidth && config.requiredHeight && showFilePanel}
    <div class="mediaclass-svelte-dimensions">
      <i class="bi bi-info-circle"></i>
      {interpolate(
        config.enforceDimensions
          ? text('dimension_requirements', 'Required dimensions: :width x :height px minimum')
          : text('dimension_recommendations', 'Recommended dimensions: :width x :height px minimum'),
        { width: config.requiredWidth, height: config.requiredHeight }
      )}
    </div>
  {/if}

  {#if limitReached}
    <div class="alert alert-warning mb-2">
      {interpolate(text('limit_reached', 'Limit of :count file(s) reached'), { count: String(config.limit) })}
    </div>
  {/if}

  {#if showFilePanel}
    <div class="mediaclass-svelte-card">
      <div class="mediaclass-svelte-filebar">
        <button type="button" class="btn btn-success btn-sm" onclick={openFileSelector} disabled={isUploading}>
          <i class="bi bi-file-earmark-plus"></i>
          {text('select', 'Select')}
        </button>
        <button type="button" class="btn btn-info btn-sm" onclick={uploadQueue} disabled={!canUpload}>
          <i class="bi bi-upload"></i>
          {text('download', 'Upload')}
        </button>
        <button type="button" class="btn btn-warning btn-sm" onclick={clearQueue} disabled={isUploading || !hasQueue}>
          <i class="bi bi-x-circle"></i>
          {text('cancel', 'Cancel')}
        </button>
        <input id={fileInputId} type="file" name="files[]" multiple accept="image/jpeg,image/png,image/svg+xml,application/pdf" onchange={handleFiles}>
      </div>

      {#if hasQueue}
        <div class="mediaclass-svelte-queue">
          {#each queue as item (item.id)}
            <article class="mediaclass-svelte-queue-item" class:error={item.status === 'error'}>
              <div class="mediaclass-svelte-preview">
                {#if item.previewUrl}
                  <img src={item.previewUrl} alt="">
                {:else}
                  <i class="bi bi-file-earmark"></i>
                {/if}
              </div>
              <div class="mediaclass-svelte-meta">
                <div class="mediaclass-svelte-fileline">
                  <strong>{item.file.name}</strong>
                  <button type="button" aria-label={text('cancel', 'Cancel')} onclick={() => removeItem(item)} disabled={item.status === 'uploading'}>
                    <i class="bi bi-x-lg"></i>
                  </button>
                </div>

                {#if item.error}
                  <p class="text-danger mb-2">{item.error}</p>
                {/if}

                {#if item.status === 'uploading'}
                  <div class="progress mediaclass-svelte-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow={item.progress}>
                    <div class="progress-bar" style={`width: ${item.progress}%`}></div>
                  </div>
                {/if}

                <div class="row params mt-3">
                  {#if config.positions}
                    <div class="col-12 positions text-center ps-2">
                      <b>{text('positions_label', 'Positions')}</b>
                      <div class="choices pt-2">
                        {#each positions as position (position)}
                          <button
                            type="button"
                            class:active={item.position === position}
                            aria-label={`${text('positions_label', 'Positions')}: ${position}`}
                            onclick={() => (item.position = position)}
                          >
                            <i class={`bi bi-arrow-${position}-square-fill`}></i>
                          </button>
                        {/each}
                      </div>
                    </div>
                  {/if}

                  {#if config.hasDescription}
                    {#each config.mediaLocales as mediaLocale (mediaLocale)}
                      <div class="col-lg-6 col-12 description">
                        <label class="form-label" for={inputId(item.id, 'description', mediaLocale)}>{text('description_label', 'Description')} ({mediaLocale})</label>
                        <textarea id={inputId(item.id, 'description', mediaLocale)} bind:value={item.descriptions[mediaLocale]} class="form-control description" rows="3"></textarea>
                      </div>
                    {/each}
                  {/if}
                </div>
              </div>
            </article>
          {/each}
        </div>
      {/if}
    </div>
  {/if}

  {#if showVideoPanel}
    <div class="mediaclass-svelte-card">
      <div class="mb-3">
        <label class="form-label" for="mediaclass-svelte-video-url">{text('video_url_label', 'Video URL')}</label>
        <input
          id="mediaclass-svelte-video-url"
          type="url"
          class="form-control"
          class:is-invalid={videoError}
          bind:value={video.url}
          placeholder={text('video_url_placeholder', 'https://...')}
        >
        {#if videoError}
          <div class="invalid-feedback d-block">{videoError}</div>
        {/if}
      </div>

      <div class="row params mt-3">
        <div class="col-12 mediaclass-video-dimensions">
          <div class="mediaclass-video-dimensions__grid">
            <div class="mediaclass-video-dimension">
              <label class="form-label" for="mediaclass-svelte-video-width-mode">{text('video_width_label', 'Video width')}</label>
              <div class="input-group">
                <select id="mediaclass-svelte-video-width-mode" bind:value={video.embedWidthMode} class="form-select mediaclass-video-width-mode">
                  <option value="pixels">{text('video_width_pixels', 'Pixels')}</option>
                  <option value="full">{text('video_width_full', '100% width')}</option>
                </select>
                <input
                  aria-label={text('video_width_label', 'Video width')}
                  type="number"
                  min="1"
                  max="7680"
                  bind:value={video.embedWidth}
                  class="form-control mediaclass-video-width"
                  disabled={video.embedWidthMode === 'full'}
                >
              </div>
            </div>
            <div class="mediaclass-video-dimension">
              <label class="form-label" for="mediaclass-svelte-video-height">{text('video_height_label', 'Video height')}</label>
              <div class="input-group">
                <select id="mediaclass-svelte-video-height-mode" bind:value={video.embedHeightMode} class="form-select mediaclass-video-height-mode">
                  <option value="auto">{text('video_height_auto', 'Auto')}</option>
                  <option value="pixels">{text('video_width_pixels', 'Pixels')}</option>
                </select>
                <input id="mediaclass-svelte-video-height" type="number" min="1" max="4320" bind:value={video.embedHeight} class="form-control mediaclass-video-height" disabled={video.embedHeightMode === 'auto'}>
              </div>
            </div>
          </div>
        </div>

        {#if config.positions}
          <div class="col-12 positions text-center ps-2">
            <b>{text('positions_label', 'Positions')}</b>
            <div class="choices pt-2">
              {#each positions as position (position)}
                <button
                  type="button"
                  class:active={video.position === position}
                  aria-label={`${text('positions_label', 'Positions')}: ${position}`}
                  onclick={() => (video.position = position)}
                >
                  <i class={`bi bi-arrow-${position}-square-fill`}></i>
                </button>
              {/each}
            </div>
          </div>
        {/if}

        {#if config.hasDescription}
          {#each config.mediaLocales as mediaLocale (mediaLocale)}
            <div class="col-lg-6 col-12 description">
              <label class="form-label" for={inputId('video', 'description', mediaLocale)}>{text('description_label', 'Description')} ({mediaLocale})</label>
              <textarea id={inputId('video', 'description', mediaLocale)} bind:value={video.descriptions[mediaLocale]} class="form-control description" rows="3"></textarea>
            </div>
          {/each}
        {/if}
      </div>

      <button type="button" class="btn btn-sm btn-warning" onclick={submitVideo} disabled={videoUploading}>
        {text('add', 'Add')}
      </button>
      <button type="button" class="btn btn-sm btn-secondary ms-2" onclick={() => setVideoPanelOpen(false)} disabled={videoUploading}>
        {text('cancel', 'Cancel')}
      </button>
    </div>
  {/if}
</div>
