import { defineConfig } from 'vite';
import { resolve } from 'path';

const sharedConfig = {
  define: { 'import.meta': '{}' },
  build: {
    outDir: resolve(__dirname, 'admin/dist'),
    emptyOutDir: false,
    rollupOptions: {
      external: ['jquery'],
      output: {
        format: 'iife',
        entryFileNames: '[name].js',
        assetFileNames: '[name][extname]',
        globals: { jquery: 'jQuery' },
      },
    },
  },
};

export default defineConfig({
  ...sharedConfig,
  build: {
    ...sharedConfig.build,
    emptyOutDir: true, // only first pass clears the folder
    lib: {
      entry: resolve(__dirname, 'admin/src/pmc-admin.js'),
      name: 'PMCCropper',
      fileName: () => 'pmc-admin.bundle.js',
      formats: ['iife'],
    },
  },
});