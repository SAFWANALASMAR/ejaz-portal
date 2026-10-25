// مسابقة الطلاب (docs/plans/student-competition.md): طلاب يبدؤون الاختبار معاً في لحظة واحدة، كبداية نافذة مرحلة
// في معامل المدارس. كل طالب: يدخل، ثم ينتظر لحظة البدء المشتركة (START_AT)، ثم «ابدأ الاختبار»، ثم يجيب كل
// الصفحات على مهل (ANSWER_SECONDS لكل صفحة)، ثم يسلّم. والتجهيز: ejaz:load-accounts ثم ejaz:load-students.
//
//   k6 run -e BASE_URL=http://localhost:8140 -e PASSWORD="$LOAD_PASSWORD" -e COMPETITION=slug -e ROUND=<uuid> \
//          -e STUDENTS=300 -e LOGIN_SPREAD=240 -e START_AT=$(( $(date +%s) + 300 )) tests/load/student-exam.js

import http from 'k6/http';
import { check, sleep } from 'k6';
import exec from 'k6/execution';
import { Counter, Trend } from 'k6/metrics';
import { BASE, guard, login, token } from './lib.js';

guard('PASSWORD', 'COMPETITION', 'ROUND', 'START_AT');

const STUDENTS = Number(__ENV.STUDENTS || 300);
const START_AT = Number(__ENV.START_AT);
const ANSWER_SECONDS = Number(__ENV.ANSWER_SECONDS || 3);
const LOGIN_SPREAD = Number(__ENV.LOGIN_SPREAD || 0);
const ROUND = `${BASE}/competitions/${__ENV.COMPETITION}/rounds/${__ENV.ROUND}`;

const received = new Counter('attempts_received');
const startLag = new Trend('start_lag_seconds');

export const options = {
    scenarios: {
        students: { executor: 'per-vu-iterations', vus: STUDENTS, iterations: 1, maxDuration: '30m' },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
        'http_req_duration{name:start}': ['p(95)<5000'],
        'http_req_duration{name:save}': ['p(95)<3000'],
        attempts_received: [`count>=${STUDENTS}`],
    },
};

// إجابة عشوائية لكل سؤال في الصفحة: أول رمز خيار لكل سؤال يكفي لاختبار الحفظ
function answers(body) {
    const form = {};

    for (const [, id, value] of body.matchAll(/name="answers\[(\d+)\]" value="([0-9a-f]{12})"/g)) {
        if (!(`answers[${id}]` in form)) {
            form[`answers[${id}]`] = value;
        }
    }

    return form;
}

export default function () {
    const n = exec.vu.idInTest;

    // الدخول موزّع على LOGIN_SPREAD ثانية قبل البدء، كوصول الطلاب إلى المعمل، والبدء نفسه في لحظة واحدة
    sleep((n % STUDENTS) * (LOGIN_SPREAD / STUDENTS));

    if (!login(n)) {
        return;
    }

    // لحظة البدء المشتركة
    const wait = START_AT - Date.now() / 1000;
    if (wait > 0) {
        sleep(wait);
    }

    let page = http.get(ROUND, { tags: { name: 'round' } });
    check(page, { 'round page': (r) => r.status === 200 });

    const started = Date.now() / 1000;
    page = http.post(`${ROUND}/start`, { _token: token(page.body) }, { tags: { name: 'start' } });
    startLag.add(Date.now() / 1000 - started);

    if (!check(page, { 'first page': (r) => r.status === 200 && /\/units\/\d+$/.test(r.url) })) {
        return;
    }

    // الصفحات بترتيب الطالب حتى المراجعة
    for (let guardPages = 0; guardPages < 60 && /\/units\/\d+$/.test(page.url); guardPages++) {
        sleep(ANSWER_SECONDS * (0.5 + Math.random()));
        page = http.post(page.url, { _token: token(page.body), go: 'next', ...answers(page.body) }, { tags: { name: 'save' } });
        check(page, { 'saved': (r) => r.status === 200 });
    }

    if (!check(page, { 'review page': (r) => r.status === 200 && r.url.endsWith('/review') })) {
        return;
    }

    page = http.post(`${ROUND}/submit`, { _token: token(page.body) }, { tags: { name: 'submit' } });

    if (check(page, { 'answers received': (r) => r.status === 200 && r.body.includes('تم استلام إجاباتك') })) {
        received.add(1);
    }
}
