import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/index.css',
                'resources/js/index.js',
                'resources/css/profile.css',
                'resources/js/profile.js',
                'resources/css/auth/login.css',
                'resources/js/auth/login.js',
                'resources/css/auth/register.css',
                'resources/js/auth/register.js',
                'resources/css/reuniones_v2.css',
                'resources/js/reuniones_v2.js',
                'resources/css/new-meeting.css',
                'resources/js/new-meeting.js',
                'resources/css/audio-processing.css',
                'resources/js/audio-processing.js',
                'resources/js/notifications.js',
                'resources/css/tasks.css',
                'resources/css/organization.css',
                'resources/js/organization.js',
            ],
            refresh: true,
        }),
    ],
});
