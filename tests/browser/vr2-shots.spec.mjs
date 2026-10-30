// لقطات قبل وبعد للدفعة الثانية (محلي، غير محفوظ في git). SHOTS_TAG=before|after
import { test } from '@playwright/test';

const tag = process.env.SHOTS_TAG ?? 'after';
const pages = [
    ['home', '/'],
    ['about', '/about'],
    ['programs', '/programs'],
    ['governance', '/governance'],
    ['governance-document', '/governance/license-certificate'],
    ['library', '/library'],
    ['media', '/media'],
    ['services', '/services'],
    ['contact', '/contact'],
    ['competition', '/competitions/brahin-1448'],
    ['login', '/login'],
    ['register', '/register'],
    ['not-found', '/vr2-missing-page'],
];

for (const [name, path] of pages) {
    test(`shot ${name}`, async ({ page }, info) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.goto(path, { waitUntil: 'networkidle' });
        await page.evaluate(async () => {
            for (let y = 0; y < document.body.scrollHeight; y += 600) {
                window.scrollTo(0, y);
                await new Promise((resolve) => setTimeout(resolve, 60));
            }
            window.scrollTo(0, 0);
        });
        await page.waitForTimeout(400);
        const width = info.project.use.viewport.width;
        await page.screenshot({ path: `docs/design/screenshots/visual-refresh-2/${name}-${tag}-${width}.png`, fullPage: true });
    });
}

test('view', async ({ page }, info) => {
    test.skip(!process.env.VIEW_PATH, 'VIEW_PATH');
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto(process.env.VIEW_PATH, { waitUntil: 'networkidle' });
    await page.evaluate((y) => window.scrollTo(0, y), Number(process.env.VIEW_Y ?? 0));
    await page.waitForTimeout(500);
    await page.screenshot({ path: `storage/vr2-view-${info.project.use.viewport.width}.png` });
});

test.describe('account shots', () => {
    test.use({ storageState: 'storage/playwright/demo-user.json' });
    for (const [name, path] of [['account', '/account'], ['account-programs', '/account/programs']]) {
        test(`shot ${name}`, async ({ page }, info) => {
            await page.emulateMedia({ reducedMotion: 'reduce' });
            await page.goto(path, { waitUntil: 'networkidle' });
            const width = info.project.use.viewport.width;
            await page.screenshot({ path: `docs/design/screenshots/visual-refresh-2/${name}-${tag}-${width}.png`, fullPage: true });
        });
    }
});
