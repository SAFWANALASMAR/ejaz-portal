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
                // خط الهوية الرسمي (دليل الهوية) بكل أوزانه، يُنزَّل عند البناء ويُقدَّم من البوابة — docs/design-system.md
                bunny('Zain', {
                    weights: [200, 300, 400, 700, 800, 900],
                    // الافتراضي latin فقط، فتسقط الحروف العربية إلى خط النظام
                    subsets: ['arabic', 'latin'],
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
            // على ويندوز لا تصل إشعارات تغيّر الملفات إلى الحاوية؛ اضبط VITE_POLLING=true في .env
            usePolling: process.env.CHOKIDAR_USEPOLLING === 'true',
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
