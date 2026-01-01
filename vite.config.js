import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/staff/reservations-index.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        // https: true,
        // host: '0.0.0.0',
        // port: 5173,
        // hmr: {
        // host: 'public.test',
        // protocol: 'wss',
        // port: 5173,
        // },
        // origin: 'https://public.test',
    },
});
