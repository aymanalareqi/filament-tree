import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    plugins: [tailwindcss()],
    build: {
        copyPublicDir: false,
        emptyOutDir: true,
        outDir: 'resources/dist',
        rollupOptions: {
            input: 'resources/css/index.css',
            output: {
                assetFileNames: 'filament-tree.css',
                entryFileNames: 'filament-tree.js',
            },
        },
    },
})
