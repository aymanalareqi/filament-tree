import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import { readFileSync, writeFileSync } from 'node:fs'

const removeGlobalTailwindProperties = () => ({
    name: 'remove-global-tailwind-properties',
    closeBundle() {
        const path = 'resources/dist/filament-tree.css'
        const css = readFileSync(path, 'utf8')
        const packageLayerStart = css.search(/@layer\s+(?:components|utilities)\s*\{/)

        if (packageLayerStart === -1) {
            throw new Error('Unable to find the package CSS layers.')
        }

        // Assets load before the panel theme, so establish Tailwind's complete
        // cascade order before emitting any of the package's layer blocks.
        writeFileSync(path, '@layer theme, base, components, utilities;\n' + css.slice(packageLayerStart))
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
