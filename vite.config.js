import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  define: {
    'import.meta': '{}',  
  },
  build: {
    // Vite will create this folder and put the final JS/CSS there
    outDir: resolve(__dirname, 'admin/dist'), 
    lib: {
      // Point this to your existing file
      entry: resolve(__dirname, 'admin/src/pmc-admin.js'), 
      name: 'PMCCropper',
      fileName: () => 'pmc-admin.bundle.js',
      formats: ['iife'] 
    },
    rollupOptions: {
      external: ['jquery'],
      output: {
        globals: {
          jquery: 'jQuery'
        }
      }
    },
    emptyOutDir: true
  }
});