import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import { readFileSync, writeFileSync } from 'node:fs'

const removeGlobalTailwindProperties = () => ({
    name: 'remove-global-tailwind-properties',
    closeBundle() {
        const path = 'resources/dist/filament-tree.css'
        const css = readFileSync(path, 'utf8')
        const utilitiesStart = css.indexOf('@layer utilities')

        if (utilitiesStart === -1) {
            throw new Error('Unable to find the Tailwind utilities layer.')
        }

        writeFileSync(path, css.slice(utilitiesStart))
    },
})

export default defineConfig({
    plugins: [tailwindcss(), removeGlobalTailwindProperties()],
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
