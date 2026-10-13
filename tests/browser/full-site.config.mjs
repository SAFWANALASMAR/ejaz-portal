// التجربة الشاملة للموقع كمستخدم حقيقي (docs/reviews/2026-10-13-full-site-test.md) على نسخة تعمل،
// لا على حاوية: الرحلات كلها، والبوابات الخمس، ولوحة الإدارة، والعرض على الجوال والحاسب.
//
// FULL_SITE_URL=http://localhost:8090 DEMO_PASSWORD=... DEMO_TOTP_SECRET=... \
//   FULL_SITE_ARTISAN="php artisan" FULL_SITE_SHOTS=docs/reviews/screenshots \
//   npx playwright test --config=tests/browser/full-site.config.mjs
//
// يكتب بيانات تجريبية، فيرفض غير localhost و127.0.0.1 و*.test إلا بـ FULL_SITE_ALLOW_REMOTE=1.
//
// متصفح Chrome المثبت (channel)، لأن Chromium في Playwright لا يشغّل H.264 (الفيديو التعريفي للاختبار).
import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    testMatch: 'full-site.spec.mjs',
    outputDir: '../../storage/playwright/full-site',
    reporter: [['list'], ['json', { outputFile: '../../storage/playwright/full-site/results.json' }]],
    // دخول واحد في كل مرة: رمز التحقق الثنائي لا يُقبل مرتين، والحسابات التجريبية تشترك في مفتاح واحد
    workers: 1,
    timeout: 300_000,
    use: {
        baseURL: process.env.FULL_SITE_URL ?? 'http://localhost:8090',
        channel: process.env.FULL_SITE_CHANNEL ?? 'chrome',
        locale: 'ar-SA',
        timezoneId: 'Asia/Riyadh',
        navigationTimeout: 60_000,
        actionTimeout: 20_000,
    },
    projects: [
        { name: 'desktop', use: { viewport: { width: 1440, height: 900 } } },
        { name: 'mobile', use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } },
    ],
});
