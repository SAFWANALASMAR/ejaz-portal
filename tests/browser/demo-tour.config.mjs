// فيديو العرض الصامت للمدير التنفيذي (demo-tour.spec.mjs) على نسخة المعاينة المحلية:
//
// FULL_SITE_URL=http://localhost:8090 DEMO_PASSWORD=... DEMO_TOTP_SECRET=... \
//   npx playwright test --config=tests/browser/demo-tour.config.mjs
//
// الفيديو في storage/playwright/demo-tour (خارج git). ومتصفح Chrome المثبت، لأن Chromium لا يشغّل H.264.
import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    testMatch: 'demo-tour.spec.mjs',
    outputDir: '../../storage/playwright/demo-tour',
    reporter: [['list']],
    workers: 1,
    timeout: 3_600_000,
    use: {
        baseURL: process.env.FULL_SITE_URL ?? 'http://localhost:8090',
        channel: process.env.FULL_SITE_CHANNEL ?? 'chrome',
        locale: 'ar-SA',
        timezoneId: 'Asia/Riyadh',
        viewport: { width: 1440, height: 900 },
        video: { mode: 'on', size: { width: 1440, height: 900 } },
        navigationTimeout: 60_000,
        actionTimeout: 20_000,
    },
});
