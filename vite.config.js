import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // خط مؤقت إلى أن تُعتمد هوية الجمعية — انظر docs/design-system.md
                bunny('IBM Plex Sans Arabic', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        // يعمل داخل حاوية، ويتصل به المتصفح عبر localhost
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: { host: 'localhost' },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
