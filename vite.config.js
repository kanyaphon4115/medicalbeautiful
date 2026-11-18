import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    base: './',
    plugins: [
        laravel({
            input: ['resources/css/responsive.css', 'resources/css/app.css', 'resources/css/procedures.css', 'resources/js/app.js', 'resources/css/hospital.css', 'resources/js/hospital.js', 'resources/js/hospital-profile.js', 'resources/css/hospital-profile.css', 'resources/css/admin.css', 'resources/js/admin/portal.js', 'resources/js/admin/chat-management.js', 'resources/js/admin/real-customers.js'],
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
