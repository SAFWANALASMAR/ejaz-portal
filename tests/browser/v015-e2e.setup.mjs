// دخول كل دور مرة واحدة وحفظ جلسته، فلا يتكرر الدخول والتحقق الثنائي في كل رحلة وعرض (حدود المحاولات).
import { mkdirSync } from 'node:fs';
import { expect, test as setup } from '@playwright/test';
import { ACCOUNTS, STATE_DIR, login, state } from './v015-e2e.helpers.mjs';

setup.skip(!ACCOUNTS, 'يحتاج accounts.json في E2E_CREDS_DIR (رأس v015-e2e.spec.mjs)');

for (const role of ['beneficiary', 'assembly', 'board', 'director']) {
    setup(`login as ${role}`, async ({ page }) => {
        mkdirSync(STATE_DIR, { recursive: true });
        await login(page, ACCOUNTS[role]);
        await expect(page).not.toHaveURL(/\/(login|two-factor-challenge)/);
        await page.context().storageState({ path: state(role) });
    });
}
