// التسجيل في برنامج (requirements/03): دخول، ثم صفحة البرنامج، ثم نموذج التسجيل، ثم الإرسال.
// يقيس قفل المقاعد تحت التزامن: لا يتجاوز المؤكدون عدد المقاعد، والبقية في قائمة الانتظار (يُفحص بعده بـ README.md).
//
//   k6 run -e BASE_URL=https://staging.example -e PROGRAM=program-slug -e PASSWORD="$LOAD_PASSWORD" \
//          -e ACCOUNTS=30000 -e PROFILE=spike -e PEAK=1000 tests/load/program-registration.js

import http from 'k6/http';
import { check, sleep } from 'k6';
import exec from 'k6/execution';
import { Counter } from 'k6/metrics';
import { ACCOUNTS, BASE, guard, login, serverOk, stages, token } from './lib.js';

guard('PASSWORD', 'PROGRAM');

const SLUG = __ENV.PROGRAM;
const registered = new Counter('registrations_answered');

export const options = {
    scenarios: {
        registrations: { executor: 'ramping-vus', startVUs: 0, stages: stages(1000), gracefulRampDown: '30s' },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
        'http_req_duration{name:register}': ['p(95)<1500'],
    },
};

export default function () {
    const n = (exec.scenario.iterationInTest % ACCOUNTS) + 1;
    const jar = http.cookieJar();

    if (!login(n)) {
        jar.clear(BASE);
        return;
    }

    const show = http.get(`${BASE}/programs/${SLUG}`, { tags: { name: 'program' } });
    check(show, { 'program 200': (r) => r.status === 200, 'no server error': serverOk });
    sleep(2 + Math.random() * 5);

    // من سجّل من قبل أو امتلأ البرنامج يُعاد إلى صفحته، وهو رد صحيح لا خطأ
    const form = http.get(`${BASE}/programs/${SLUG}/register`, { tags: { name: 'register-form' } });
    check(form, { 'register form': (r) => r.status === 200, 'no server error': serverOk });

    if (!form.url.endsWith('/register')) {
        jar.clear(BASE);
        return;
    }

    // الحقول الإضافية النصية تُملأ بنص اختبار. ويُفضَّل برنامج اختبار بلا حقول إلزامية من نوع آخر
    const body = { _token: token(form.body) };
    for (const [, key] of form.body.matchAll(/<(?:input|textarea)[^>]*name="answers\[([^\]]+)\]"/g)) {
        body[`answers[${key}]`] ??= `اختبار التحمل ${n}`;
    }

    sleep(5 + Math.random() * 10);

    const response = http.post(`${BASE}/programs/${SLUG}/register`, body, { redirects: 0, tags: { name: 'register' } });
    const ok = check(response, {
        'registration answered': (r) => r.status === 302 && String(r.headers.Location || '').endsWith(`/programs/${SLUG}`),
        'no server error': serverOk,
    });

    if (ok) {
        registered.add(1);
    }

    jar.clear(BASE);
}
