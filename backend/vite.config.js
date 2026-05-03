import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/quiz-app.jsx',
                'resources/js/warashibe-app.jsx',
                'resources/js/puzzle-app.jsx',
                'resources/js/subaracity-app.jsx',
            ],
            refresh: true,
        }),
        react(),
    ],
});
