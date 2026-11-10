import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    base: './',
    plugins: [
        laravel({
            input: ['resources/css/responsive.css', 'resources/css/app.css', 'resources/css/procedures.css', 'resources/js/app.js', 'resources/css/hospital.css', 'resources/js/hospital.js', 'resources/css/admin.css', 'resources/js/admin/portal.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
