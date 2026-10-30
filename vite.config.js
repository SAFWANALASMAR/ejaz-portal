import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // والثالث سمة لوحة الإدارة (viteTheme في AdminPanelProvider)
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament/admin/theme.css'],
            refresh: true,
            fonts: [
                // خط الهوية الرسمي (دليل الهوية) بكل أوزانه، يُنزَّل عند البناء ويُقدَّم من البوابة — docs/design-system.md
                bunny('Zain', {
                    weights: [200, 300, 400, 700, 800, 900],
                    // الافتراضي latin فقط، فتسقط الحروف العربية إلى خط النظام
                    subsets: ['arabic', 'latin'],
                    // التحميل المسبق (woff2 وحده) للوزنين المستعملين أعلى الصفحة: 400 للنص والقائمة، و700 للعناوين والأزرار
                    // (bold وsemibold). والأوزان الأخرى عند الحاجة، فلا تنزيل زائد ولا تحذير «preloaded but not used»
                    preload: [{ weight: 400 }, { weight: 700 }],
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
