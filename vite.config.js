import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Tajawal', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        tailwindcss(),
        react(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    build: {
        // Stop Vite from eagerly <link rel="modulepreload">-ing every
        // dynamically-imported page chunk on initial load. Route-level
        // code splitting (import.meta.glob in app.jsx) should fetch a page's
        // chunk only when that page is actually navigated to. Disabling
        // dependency resolution stops Vite's runtime preloader from downgrading
        // all lazy page chunks into eager preloads on every page load.
        modulePreload: {
            polyfill: false,
            resolveDependencies: () => [],
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
