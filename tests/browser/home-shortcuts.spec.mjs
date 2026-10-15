// أسهم بطاقات الأقسام في الرئيسية متحاذية: البطاقة عمود بارتفاع الصف، والسهم في أسفلها (mt-auto)، فلا يتبع
// موضعه طول الوصف. على العروض الثلاثة، ولقطة للقسم في docs/design/screenshots (SHOT_LABEL: before أو after).
import { expect, test } from '@playwright/test';

test('aligns the arrows of the section cards on every row', async ({ page }, testInfo) => {
    await page.goto('/');

    const section = page.locator('section[aria-labelledby="home-shortcuts"]');
    await expect(section).toBeVisible();

    // اللقطة قبل التحقق، فتُحفظ لقطة «قبل» ولو فشل. والرأس الثابت لا يغطي القسم الأطول من الشاشة في اللقطة
    await page.addStyleTag({ content: '[data-site-header] { position: static !important; }' });
    const width = page.viewportSize().width;
    await section.screenshot({ path: `docs/design/screenshots/home-shortcuts-${process.env.SHOT_LABEL ?? 'after'}-${width}.png` });

    const rows = await section.evaluate((element) => {
        const byRow = new Map();

        for (const item of element.querySelectorAll('ul > li')) {
            const top = Math.round(item.getBoundingClientRect().top);
            const arrow = item.querySelector('a > span:last-child').getBoundingClientRect();
            byRow.set(top, [...(byRow.get(top) ?? []), Math.round(arrow.top)]);
        }

        return [...byRow.values()];
    });

    expect(rows.length).toBeGreaterThan(0);

    for (const arrows of rows) {
        expect(Math.max(...arrows) - Math.min(...arrows), `arrow tops in one row: ${arrows.join(', ')}`).toBeLessThanOrEqual(1);
    }

    testInfo.annotations.push({ type: 'rows', description: JSON.stringify(rows) });
});
