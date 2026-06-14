import { svelte } from '@sveltejs/vite-plugin-svelte';
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [svelte()],
  publicDir: false,
  build: {
    cssCodeSplit: true,
    emptyOutDir: false,
    lib: {
      cssFileName: 'mediaclass-uploader',
      entry: 'mediaclass-uploader.ts',
      name: 'MediaclassSvelteUploader',
      formats: ['iife'],
      fileName: () => 'mediaclass-uploader.js'
    },
    outDir: '../../public/vendor/mfw-mediaclass',
    rollupOptions: {
      output: {
        assetFileNames: (assetInfo) => {
          if (assetInfo.name === 'style.css') {
            return 'mediaclass-uploader.css';
          }

          return '[name][extname]';
        }
      }
    }
  }
});
