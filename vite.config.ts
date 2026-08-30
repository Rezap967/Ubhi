import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
    server: {
        proxy: {
            '/api': {
                target: process.env.APP_URL?.replace(/\/$/, '') || 'http://127.0.0.1:8000',
                changeOrigin: true,
                secure: false,
            },
            '/sanctum': {
                target: process.env.APP_URL?.replace(/\/$/, '') || 'http://127.0.0.1:8000',
                changeOrigin: true,
                secure: false,
            },
            '/login': {
                target: process.env.APP_URL?.replace(/\/$/, '') || 'http://127.0.0.1:8000',
                changeOrigin: true,
                secure: false,
            },
            '/logout': {
                target: process.env.APP_URL?.replace(/\/$/, '') || 'http://127.0.0.1:8000',
                changeOrigin: true,
                secure: false,
            },
            '/register': {
                target: process.env.APP_URL?.replace(/\/$/, '') || 'http://127.0.0.1:8000',
                changeOrigin: true,
                secure: false,
            },
            '/forgot-password': {
                target: process.env.APP_URL?.replace(/\/$/, '') || 'http://127.0.0.1:8000',
                changeOrigin: true,
                secure: false,
            },
            '/password': {
                target: process.env.APP_URL?.replace(/\/$/, '') || 'http://127.0.0.1:8000',
                changeOrigin: true,
                secure: false,
            },
        },
    },
});
