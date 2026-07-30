import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        // No `fonts` entry: the type stack (Helvetica Neue → Helvetica → Arial → sans-serif,
        // see resources/css/app.css) is resolved from fonts already on the host, so there is
        // nothing to download. Dropping the webfont pipeline also drops twelve font files and
        // a render-blocking stylesheet from the build.
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
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
