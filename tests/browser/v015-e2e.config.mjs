// اختبار الرحلات الكاملة قبل الإصدار 0.15.0 (v015-e2e.spec.mjs) على عرضي 1440 و390 وحدهما.
// مشروع «setup» يدخل بكل دور مرة واحدة ويحفظ جلسته في storage/playwright/v015/ (حدود الدخول والتحقق الثنائي)،
// ثم تعمل الرحلات بالجلسات المحفوظة. التشغيل وبيانات الحسابات في رأس v015-e2e.spec.mjs.
import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    testMatch: ['v015-e2e.setup.mjs', 'v015-e2e.spec.mjs'],
    outputDir: '../../storage/playwright/v015-results',
    globalSetup: './global-setup.mjs',
    reporter: [['list'], ['json', { outputFile: '../../storage/playwright/v015-report.json' }]],
    workers: 1,
    timeout: 420_000,
    expect: { timeout: 15_000 },
    use: {
        baseURL: `http://localhost:${process.env.APP_PORT ?? 8000}`,
        locale: 'ar-SA',
        timezoneId: 'Asia/Riyadh',
        navigationTimeout: 180_000,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'setup', testMatch: 'v015-e2e.setup.mjs', use: { viewport: { width: 1440, height: 900 } } },
        { name: 'desktop', testMatch: 'v015-e2e.spec.mjs', dependencies: ['setup'], use: { viewport: { width: 1440, height: 900 } } },
        { name: 'mobile', testMatch: 'v015-e2e.spec.mjs', dependencies: ['setup'], use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } },
    ],
});
