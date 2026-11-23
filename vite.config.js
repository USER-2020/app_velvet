import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';
import { fileURLToPath } from 'node:url';

const resourcesPath = fileURLToPath(new URL('./resources/js', import.meta.url));

export default defineConfig({
    resolve: {
        alias: {
            '@': resourcesPath,
        },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
        VitePWA({
            registerType: 'prompt',
            injectRegister: false,
            includeAssets: ['favicon.ico', 'LOGO_OVERLAY.svg', 'pwa-192x192.png', 'pwa-512x512.png'],
            manifest: {
                name: 'Velvet Master',
                short_name: 'Velvet Master',
                description: 'Gestión operativa de The Velvet Studio.',
                lang: 'es-CO',
                start_url: '/dashboard',
                scope: '/',
                display: 'standalone',
                orientation: 'any',
                theme_color: '#090a10',
                background_color: '#090a10',
                icons: [
                    {
                        src: '/pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any maskable',
                    },
                ],
            },
            workbox: {
                cleanupOutdatedCaches: true,
                navigateFallback: null,
                globPatterns: ['**/*.{js,css,svg,png,webp,ico,woff,woff2}'],
            },
            devOptions: { enabled: false },
        }),
    ],
});
