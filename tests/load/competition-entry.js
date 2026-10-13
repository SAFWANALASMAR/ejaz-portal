// اختبار تحمل التقديم في مسابقة (docs/performance.md): كل مستخدم افتراضي يدخل بحساب اختبار، ويفتح
// نموذج المشاركة، ويحفظ مسودته، ويرفع مقطعاً مجزأً لكل سؤال فيديو، ثم يرسل. والنجاح: لا أخطاء خادم،
// ولا تقديم مكرر (يُفحص بعده بالأمر في README.md).
//
// التشغيل (على البيئة التجريبية لا على جهاز التطوير):
//   k6 run -e BASE_URL=https://staging.example -e COMPETITION=slug -e PASSWORD=... \
//          -e ACCOUNTS=30000 -e VIDEO=./sample.mp4 tests/load/competition-entry.js

import http from 'k6/http';
import { check, fail } from 'k6';
import exec from 'k6/execution';
import { Counter } from 'k6/metrics';
import { guard } from './lib.js';

// المشاركات التي أُرسلت فعلاً برقم مرجعي، لتُقارن بعدد الحسابات
const confirmed = new Counter('entries_confirmed');

const BASE = String(__ENV.BASE_URL || '').replace(/\/+$/, '');
const SLUG = __ENV.COMPETITION;
const PASSWORD = __ENV.PASSWORD;
const ACCOUNTS = Number(__ENV.ACCOUNTS || 30000);
const VIDEO = __ENV.VIDEO ? open(__ENV.VIDEO, 'b') : null;

export const options = {
    scenarios: {
        // كل حساب يقدّم مرة واحدة، والمعدل يرتفع إلى ذروة ثم يستقر. والمدة وتوزيع الدخول من الجمعية
        // حسب تجارب التسجيل السابقة (【ناقص】 في performance.md)، فتُضبط بالمتغيرات
        entries: {
            executor: 'shared-iterations',
            vus: Number(__ENV.VUS || 500),
            iterations: ACCOUNTS,
            maxDuration: __ENV.DURATION || '2h',
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
        'http_req_duration{step:page}': ['p(95)<1500'],
    },
};

function token(body) {
    const match = /<meta name="csrf-token" content="([^"]+)"/.exec(body);

    return match ? match[1] : fail('no csrf token');
}

function serverOk(response) {
    return response.status < 500;
}

// لا تشغيل على الإنتاج، والمتغيرات المطلوبة موجودة (lib.js)
export function setup() {
    guard('COMPETITION', 'PASSWORD');
}

export default function () {
    // رقم الحساب من رقم التكرار، فلا يكرر مستخدمان الحساب نفسه
    const n = exec.scenario.iterationInTest + 1;
    const jar = http.cookieJar();

    let page = http.get(`${BASE}/login`, { tags: { step: 'page' } });
    check(page, { 'login page': (r) => r.status === 200 });

    const login = http.post(`${BASE}/login`, { _token: token(page.body), login: `load-${n}@load.test`, password: PASSWORD }, { redirects: 0 });
    check(login, { 'logged in': (r) => r.status === 302 && !String(r.headers.Location || '').includes('/login') });

    // المسودة تُنشأ بزر «شارك الآن» (POST) في صفحة المسابقة، ثم يُعاد التوجيه إلى النموذج
    page = http.get(`${BASE}/competitions/${SLUG}`, { tags: { step: 'page' } });
    page = http.post(`${BASE}/competitions/${SLUG}/entry/start`, { _token: token(page.body) }, { tags: { step: 'page' } });
    if (!check(page, { 'entry form': (r) => r.status === 200 && r.url.endsWith('/entry') })) {
        return;
    }

    const csrf = token(page.body);
    const form = { category: (/<option value="([^"]+)"/.exec(page.body.split('name="category"')[1] || '') || [])[1] || '', region: 'riyadh' };

    for (const [, id] of page.body.matchAll(/<textarea id="answer-\d+" name="answers\[(\d+)\]"/g)) {
        form[`answers[${id}]`] = `إجابة اختبار التحمل ${n}`;
    }

    for (const [, id, value] of page.body.matchAll(/name="answers\[(\d+)\]" value="([a-j])"/g)) {
        form[`answers[${id}]`] ??= value;
    }

    const headers = { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' };

    const autosave = http.post(`${BASE}/competitions/${SLUG}/entry/autosave`, form, { headers });
    check(autosave, { 'autosaved': (r) => r.status === 200, 'no server error': serverOk });

    if (VIDEO) {
        for (const [, url] of page.body.matchAll(/data-video-upload="([^"]+)"/g)) {
            const start = http.post(url.replaceAll('&amp;', '&'), JSON.stringify({ size: VIDEO.byteLength, type: 'video/mp4', duration: 1 }), {
                headers: { ...headers, 'Content-Type': 'application/json' },
            });

            if (!check(start, { 'upload started': (r) => r.status === 200 })) {
                continue;
            }

            const { video, chunk_size: size, urls } = start.json();

            for (const [index, chunkUrl] of Object.entries(urls)) {
                const body = VIDEO.slice(Number(index) * size, Math.min(VIDEO.byteLength, (Number(index) + 1) * size));
                const put = http.put(chunkUrl, body, { headers: { ...headers, 'Content-Type': 'application/octet-stream' } });
                check(put, { 'chunk stored': (r) => r.status === 204, 'no server error': serverOk });
            }

            const done = http.post(`${BASE}/competition-videos/${video}/complete`, null, { headers });
            check(done, { 'upload completed': (r) => r.status === 202 });
        }
    }

    const submit = http.post(`${BASE}/competitions/${SLUG}/entry/submit`, { _token: csrf, ...form }, { redirects: 0 });
    check(submit, { 'submit answered': (r) => r.status === 302, 'no server error': serverOk });

    // الرد 302 في النجاح وفي رفض التحقق معاً، فالتأكيد من الرقم المرجعي في صفحة المشاركة بعده
    const after = http.get(`${BASE}/competitions/${SLUG}/entry`, { tags: { step: 'page' } });

    if (/dir="ltr">[A-HJ-NP-Z2-9]{10}</.test(after.body)) {
        confirmed.add(1);
    }

    jar.clear(BASE);
}
