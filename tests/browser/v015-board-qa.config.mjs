// فحص بوابة عضو المجلس (v015-board-qa.spec.mjs): الدخول مرة ثم الزحف على عرضي 1440 و390.
import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    testMatch: 'v015-board-qa.spec.mjs',
    outputDir: '../../storage/playwright/board-qa-results',
    globalSetup: './global-setup.mjs',
    reporter: [['list']],
    workers: 1,
    timeout: 420_000,
    expect: { timeout: 15_000 },
    use: {
        baseURL: `http://localhost:${process.env.APP_PORT ?? 8000}`,
        locale: 'ar-SA',
        timezoneId: 'Asia/Riyadh',
        navigationTimeout: 120_000,
    },
    projects: [
        { name: 'setup', grep: /login and save/, use: { viewport: { width: 1440, height: 900 } } },
        { name: 'desktop', grep: /every reachable/, dependencies: ['setup'], use: { viewport: { width: 1440, height: 900 } } },
        { name: 'mobile', grep: /every reachable/, dependencies: ['setup'], use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } },
    ],
});
