import { readFileSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

// بديلا فك صور JPEG 2000 وJBIG2 بلا WebAssembly في pdf.js: عامل القارئ يطلبهما باسميهما من مجلد واحد (wasmUrl في pdf-reader.js)، فيُنسخان
// بلا بصمة في مجلد باسم إصدار المكتبة، فيتغير المجلد مع كل ترقية ولا يبقى ملف قديم في التخزين الطويل (docs/decisions/2026-11-13-fix-reader-jpeg2000.md)
const pdfjsVersion = JSON.parse(readFileSync(new URL('./node_modules/pdfjs-dist/package.json', import.meta.url), 'utf8')).version;
const noWasmFallback = (asset) => (asset.names ?? [asset.name]).some((name) => name?.endsWith('_nowasm_fallback.js'));

export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                assetFileNames: (asset) => (noWasmFallback(asset) ? `assets/pdfjs-${pdfjsVersion}/[name][extname]` : 'assets/[name]-[hash][extname]'),
            },
        },
    },
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
