// أدوات مشتركة لاختبار الرحلات قبل الإصدار 0.15.0: الحسابات من ملفات JSON خارج git، والدخول بالتحقق الثنائي،
// ومراقبة كل صفحة (أخطاء وحدة التحكم، والاستجابات 4xx/5xx غير المتوقعة، والتمرير الأفقي، والاتجاه من اليمين).
import { createHmac } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { expect } from '@playwright/test';

// ملفات الحسابات (كلمات مرور لهذا التشغيل): accounts.json من v015-e2e.fixtures.php (رأس v015-e2e.spec.mjs)،
// وstudent-competition.json من ejaz:demo-student-competition --json. المجلد مستثنى من git.
export const CREDS_DIR = process.env.E2E_CREDS_DIR ?? 'storage/playwright/v015-creds';
export const STATE_DIR = 'storage/playwright/v015';
export const SHOTS_DIR = 'storage/playwright/v015-shots';

function readJson(name) {
    const path = `${CREDS_DIR}/${name}`;
    return existsSync(path) ? JSON.parse(readFileSync(path, 'utf8')) : null;
}

export const ACCOUNTS = readJson('accounts.json');
export const STUDENTS = readJson('student-competition.json');
export const state = (role) => `${STATE_DIR}/${role}.json`;

// ── TOTP (RFC 6238): الرمز لا يُقبل مرتين للحساب نفسه، فتُحفظ آخر نافذة لكل حساب
function base32(secret) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const char of secret.replace(/=+$/, '')) bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
    const bytes = [];
    for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2));
    return Buffer.from(bytes);
}

export async function freshCode(account) {
    const file = `${STATE_DIR}/totp-window-${account.email.replace(/[^a-z0-9]/gi, '_')}`;
    const lastWindow = existsSync(file) ? Number(readFileSync(file, 'utf8')) : -1;
    let window = Math.floor(Date.now() / 30_000);
    while (window <= lastWindow || Date.now() % 30_000 > 25_000) {
        await new Promise((resolve) => setTimeout(resolve, 1000));
        window = Math.floor(Date.now() / 30_000);
    }
    mkdirSync(STATE_DIR, { recursive: true });
    writeFileSync(file, String(window));
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(window));
    const hmac = createHmac('sha1', base32(account.totp_secret)).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 15;
    return String((hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000).padStart(6, '0');
}

export async function login(page, account) {
    await page.goto('/login');
    await page.fill('#field-login', account.email);
    await page.fill('#field-password', account.password);
    await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/login'), { waitUntil: 'commit' }), page.locator('main form button[type=submit]').first().click()]);

    if (page.url().includes('two-factor-challenge')) {
        await page.fill('input[name="code"]', await freshCode(account));
        await Promise.all([page.waitForURL((url) => !url.pathname.includes('two-factor-challenge'), { waitUntil: 'commit' }), page.locator('main form button[type=submit]').first().click()]);
    }
}

/**
 * يراقب الصفحة طوال الرحلة: أخطاء وحدة التحكم وأخطاء JavaScript، وكل استجابة من البوابة نفسها برمز 400 فأعلى
 * إلا ما يتوقعه الاختبار (allow: مسارات أو تعابير). والطلبات الخارجية (يوتيوب وغيرها) خارج الفحص.
 */
export function watch(page, baseURL) {
    const origin = new URL(baseURL).origin;
    const watcher = { console: [], network: [], allow: [] };

    page.on('pageerror', (error) => watcher.console.push(`pageerror: ${error.message}`));
    page.on('console', (message) => {
        if (message.type() === 'error') {
            watcher.console.push(`${message.text()} @ ${message.location()?.url ?? ''}`);
        }
    });
    page.on('response', (response) => {
        const url = new URL(response.url());
        if (url.origin !== origin || response.status() < 400) return;
        if (watcher.allow.some((rule) => (rule instanceof RegExp ? rule.test(url.pathname) : url.pathname === rule))) return;
        watcher.network.push(`${response.status()} ${response.request().method()} ${url.pathname}${url.search}`);
    });

    return watcher;
}

// أخطاء وحدة التحكم المتوقعة حين يرد الخادم 403/404/422 عمداً (المتصفح يسجّل «Failed to load resource» لكل رد خطأ)
const expectedConsole = (text, watcher) => /Failed to load resource: the server responded with a status of (403|404|419|422)/.test(text) && watcher.allow.length > 0;

// خلل معروف مختبر وحده في «moving from the site to the panel raises no view-transition error» (تقرير الاختبار 2026-11-03، البند ٢)،
// فلا يُسقط بقية الرحلة التي تمر به
export const KNOWN_VIEW_TRANSITION_ERROR = 'Transition was aborted because of invalid state';

/**
 * فحوص كل خطوة: الاتجاه من اليمين، وبلا تمرير أفقي، وبلا أخطاء في وحدة التحكم، وبلا استجابات خطأ غير متوقعة.
 * فحوص ناعمة (expect.soft) لتُجمع كل الإخفاقات في الرحلة، ولقطة عند أي إخفاق في storage/playwright/v015-shots.
 */
export async function checkStep(page, testInfo, watcher, step) {
    await page.waitForLoadState('load');
    const width = testInfo.project.use.viewport.width;
    const failures = [];

    const dir = await page.locator('html').getAttribute('dir');
    if (dir !== 'rtl') failures.push(`dir=${dir}`);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    if (overflow > 0) {
        const culprits = await page.evaluate(() => {
            const limit = document.documentElement.clientWidth;
            return [...document.querySelectorAll('body *')]
                .filter((element) => {
                    const box = element.getBoundingClientRect();
                    return box.width > 0 && (box.right > limit + 1 || box.left < -1);
                })
                .slice(0, 5)
                .map((element) => `${element.tagName.toLowerCase()}${element.id ? '#' + element.id : ''}.${[...element.classList].slice(0, 3).join('.')}`);
        });
        failures.push(`horizontal scroll ${overflow}px: ${culprits.join(' | ')}`);
    }

    const consoleErrors = watcher.console.filter((text) => !expectedConsole(text, watcher) && !(watcher.knownViewTransition && text.includes(KNOWN_VIEW_TRANSITION_ERROR)));
    if (consoleErrors.length) failures.push(`console: ${consoleErrors.join(' || ')}`);
    if (watcher.network.length) failures.push(`network: ${watcher.network.join(' || ')}`);

    if (failures.length) {
        mkdirSync(SHOTS_DIR, { recursive: true });
        const path = `${SHOTS_DIR}/${testInfo.title.replace(/[^a-z0-9]+/gi, '-').slice(0, 60)}-${step}-${width}.png`;
        await page.screenshot({ path, fullPage: true }).catch(() => {});
        testInfo.annotations.push({ type: 'step-failure', description: `${step} (${page.url()}): ${failures.join(' ;; ')} [shot: ${path}]` });
    }

    expect.soft(failures, `${step} @ ${new URL(page.url()).pathname} (${width}px)`).toEqual([]);
    watcher.console.length = 0;
    watcher.network.length = 0;
}

export async function visit(page, testInfo, watcher, path, step = path) {
    const response = await page.goto(path);
    await checkStep(page, testInfo, watcher, step);
    return response;
}
