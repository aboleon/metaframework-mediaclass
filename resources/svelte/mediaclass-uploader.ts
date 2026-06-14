import { mount } from 'svelte';
import Uploadable from './Uploadable.svelte';

const mounted = new WeakMap<Element, ReturnType<typeof mount>>();

export function init(root: ParentNode = document): void {
  root.querySelectorAll('.mediaclass-svelte-uploader').forEach((target) => {
    if (mounted.has(target)) {
      return;
    }

    mounted.set(target, mount(Uploadable, {
      target,
      props: { host: target as HTMLElement }
    }));
  });
}

if (typeof window !== 'undefined') {
  window.MediaclassSvelteUploader = { init };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => init());
  } else {
    init();
  }
}
