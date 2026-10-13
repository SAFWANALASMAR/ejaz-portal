// أدوات مشتركة لسكربتات k6 في tests/load (README.md).

import http from 'k6/http';
import { check, fail } from 'k6';

export const BASE = String(__ENV.BASE_URL || '').replace(/\/+$/, '');
export const PASSWORD = __ENV.PASSWORD;
export const ACCOUNTS = Number(__ENV.ACCOUNTS || 30000);

// لا تشغيل على الإنتاج أبداً (قواعد الاشتباك في .claude/skills/security-audit وperformance.md)
const FORBIDDEN_HOSTS = ['ejaz.sa', 'www.ejaz.sa'];

export function guard(...required) {
    if (!BASE) {
        fail('BASE_URL مطلوب، وهو APP_URL للبيئة التجريبية نفسه');
    }

    const host = BASE.replace(/^https?:\/\//, '').split(/[/:]/)[0].toLowerCase();

    if (FORBIDDEN_HOSTS.includes(host)) {
        fail('ممنوع تشغيل اختبار التحمل على الإنتاج');
    }

    for (const name of required) {
        if (!__ENV[name]) {
            fail(`المتغير ${name} مطلوب`);
        }
    }
}

// ملفات التصاعد بالمستخدمين الافتراضيين. PEAK هو التزامن في الذروة، ويُحسب من README.md
export function stages(defaultPeak) {
    const peak = Number(__ENV.PEAK || defaultPeak);
    const quarter = Math.max(1, Math.round(peak / 4));

    const profiles = {
        // تجربة السكربت نفسه، لا اختبار تحمل
        smoke: [{ duration: '1m', target: 2 }],
        // الحمل العادي: ربع الذروة
        average: [
            { duration: '5m', target: quarter },
            { duration: '15m', target: quarter },
            { duration: '2m', target: 0 },
        ],
        // الذروة المتوقعة: تصاعد تدريجي ثم ثبات
        peak: [
            { duration: '10m', target: peak },
            { duration: '20m', target: peak },
            { duration: '5m', target: 0 },
        ],
        // لحظة فتح التسجيل: قفزة مفاجئة إلى الذروة
        spike: [
            { duration: '1m', target: peak },
            { duration: '5m', target: peak },
            { duration: '1m', target: 0 },
        ],
        // ما بعد الذروة: إلى مثليها لمعرفة نقطة الانكسار (لا يدخل في معايير القبول)
        stress: [
            { duration: '10m', target: peak },
            { duration: '10m', target: peak * 2 },
            { duration: '5m', target: 0 },
        ],
    };

    const name = __ENV.PROFILE || 'smoke';

    if (!profiles[name]) {
        fail(`PROFILE غير معروف: ${name} (smoke، average، peak، spike، stress)`);
    }

    return profiles[name];
}

export function token(body) {
    const match = /<meta name="csrf-token" content="([^"]+)"/.exec(body || '');

    return match ? match[1] : fail('no csrf token');
}

export function serverOk(response) {
    return response.status < 500;
}

// حساب الاختبار رقم n من ejaz:load-accounts
export function account(n) {
    return `load-${n}@load.test`;
}

export function login(n) {
    const page = http.get(`${BASE}/login`, { tags: { step: 'page', name: 'login-page' } });
    check(page, { 'login page': (r) => r.status === 200 });

    const response = http.post(`${BASE}/login`, { _token: token(page.body), login: account(n), password: PASSWORD }, {
        redirects: 0,
        tags: { name: 'login' },
    });

    return check(response, {
        'logged in': (r) => r.status === 302 && !String(r.headers.Location || '').includes('/login'),
        'no server error': serverOk,
    });
}
