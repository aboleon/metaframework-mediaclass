/// <reference types="svelte" />
/// <reference types="vite/client" />

declare global {
  interface Window {
    MediaclassUploader?: MediaclassLegacyBridge;
    MediaclassSvelteUploader?: {
      init(root?: ParentNode): void;
    };
    jQuery?: JQueryStatic;
    notificator?: (
      status: number | string,
      messages: unknown,
      target?: unknown,
      persistent?: boolean,
      options?: Record<string, unknown>
    ) => void;
  }

  interface MediaclassLegacyBridge {
    appendUploadedMedia(
      uploadable: JQuery<HTMLElement>,
      data: UploadResponse,
      hideDescription: boolean
    ): void;
    executeCallback(uploadable: JQuery<HTMLElement>, data: UploadResponse): void;
    i18nText(uploadable: JQuery<HTMLElement>, key: string, fallback?: string): string;
    printResponseMessages(uploadable: JQuery<HTMLElement>, data: UploadResponse): void;
  }

  interface UploadResponse {
    error?: unknown;
    errors?: unknown;
    mfw_ajax_messages?: unknown;
    messages?: unknown;
    uploaded?: {
      id: number;
      original_filename: string;
      created_at: string;
      description?: Record<string, string>;
      position?: string;
      sort_order?: number;
      storable?: Record<string, unknown>;
    };
    [key: string]: unknown;
  }
}

export {};
