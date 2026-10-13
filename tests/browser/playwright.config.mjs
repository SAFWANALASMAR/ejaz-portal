// اختبارات المتصفح ولقطات الشاشات على العروض الثلاثة (docs/design/ui-reference.md).
// التشغيل: انظر tests/browser/README.md
import { defineConfig } from '@playwright/test';

const widths = {
    desktop: { width: 1440, height: 900 },
    tablet: { width: 820, height: 1180 },
    mobile: { width: 390, height: 844 },
};

export default defineConfig({
    testDir: '.',
    // التجربة الشاملة وجولة العرض لكل منهما إعداده وبيئته (full-site.config.mjs وdemo-tour.config.mjs)
    testIgnore: ['full-site.spec.mjs', 'demo-tour.spec.mjs'],
    outputDir: '../../storage/playwright/results',
    globalSetup: './global-setup.mjs',
    reporter: [['list']],
    workers: 1,
    timeout: 120_000,
    use: {
        // البوابة ووحدة Vite تُنقلان إلى هذين المنفذين داخل الحاوية (global-setup.mjs)،
        // فيرى المتصفح العناوين نفسها التي يراها صفوان في متصفحه
        baseURL: `http://localhost:${process.env.APP_PORT ?? 8000}`,
        locale: 'ar-SA',
        timezoneId: 'Asia/Riyadh',
        navigationTimeout: 90_000,
    },
    projects: Object.entries(widths).map(([name, viewport]) => ({
        name,
        use: { viewport, isMobile: name === 'mobile', hasTouch: name !== 'desktop' },
    })),
});
