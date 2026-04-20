import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  define: { 'import.meta': '{}' },
  build: {
    outDir: resolve(__dirname, 'admin/dist'),
    emptyOutDir: false, // don't wipe what the first pass built
    lib: {
      entry: resolve(__dirname, 'admin/src/pmc-media-tab.js'),
      name: 'PMCMediaTab',
      fileName: () => 'pmc-media-tab.js',
      formats: ['iife'],
    },
    rollupOptions: {
      external: ['jquery'],
      output: {
        globals: { jquery: 'jQuery' },
      },
    },
  },
});