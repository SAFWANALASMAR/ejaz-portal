// رحلة المتقدم في المسابقة على امتداد وقت الإجابة (requirements/06): دخول، ثم قائمة المسابقات وصفحتها،
// ثم «شارك الآن»، ثم إجابة على مهل بحفظ تلقائي كل 30–60 ثانية طوال EXAM_MINUTES، ثم الإرسال والتأكد من الرقم المرجعي.
//
// يكمّل competition-entry.js: ذاك يقيس دفعة التقديم والرفع، وهذا يقيس آلاف الجلسات المفتوحة معاً بحفظها التلقائي.
// يستهدف مسارات المشاركة القائمة (/competitions/{slug}/entry/*). وإن دُمج فرع الاختبار المؤقت (exam) فتُضاف مساراته هنا.
//
//   k6 run -e BASE_URL=https://staging.example -e COMPETITION=slug -e PASSWORD="$LOAD_PASSWORD" \
//          -e ACCOUNTS=30000 -e EXAM_MINUTES=20 -e PROFILE=peak -e PEAK=3000 tests/load/competition-exam.js

import http from 'k6/http';
import { check, sleep } from 'k6';
import exec from 'k6/execution';
import { Counter } from 'k6/metrics';
import { ACCOUNTS, BASE, guard, login, serverOk, stages, token } from './lib.js';

guard('PASSWORD', 'COMPETITION');

const SLUG = __ENV.COMPETITION;
const EXAM_SECONDS = Number(__ENV.EXAM_MINUTES || 20) * 60;
const confirmed = new Counter('entries_confirmed');

export const options = {
    scenarios: {
        candidates: { executor: 'ramping-vus', startVUs: 0, stages: stages(3000), gracefulRampDown: `${EXAM_SECONDS + 60}s` },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
        'http_req_duration{name:autosave}': ['p(95)<1000'],
        'http_req_duration{name:submit}': ['p(95)<2000'],
        'http_req_duration{step:page}': ['p(95)<1500'],
    },
};

function fields(body, n) {
    const form = { category: (/<option value="([^"]+)"/.exec(body.split('name="category"')[1] || '') || [])[1] || '' };
    const region = (/<option value="([^"]+)"/.exec(body.split('name="region"')[1] || '') || [])[1];

    if (region) {
        form.region = region;
    }

    for (const [, id] of body.matchAll(/<textarea id="answer-\d+" name="answers\[(\d+)\]"/g)) {
        form[`answers[${id}]`] = `إجابة اختبار التحمل ${n}`;
    }

    for (const [, id, value] of body.matchAll(/name="answers\[(\d+)\]" value="([a-j])"/g)) {
        form[`answers[${id}]`] ??= value;
    }

    return form;
}

export default function () {
    const n = (exec.scenario.iterationInTest % ACCOUNTS) + 1;
    const jar = http.cookieJar();

    if (!login(n)) {
        jar.clear(BASE);
        return;
    }

    check(http.get(`${BASE}/competitions`, { tags: { step: 'page', name: 'competitions' } }), { 'list 200': (r) => r.status === 200 });

    let page = http.get(`${BASE}/competitions/${SLUG}`, { tags: { step: 'page', name: 'competition' } });
    check(page, { 'competition 200': (r) => r.status === 200 });
    sleep(5 + Math.random() * 10);

    // المسودة بزر «شارك الآن» (POST)، ثم يُعاد التوجيه إلى النموذج. ومن أرسل من قبل يرى مشاركته
    page = http.post(`${BASE}/competitions/${SLUG}/entry/start`, { _token: token(page.body) }, { tags: { step: 'page', name: 'start' } });
    if (!check(page, { 'entry form': (r) => r.status === 200 && r.url.endsWith('/entry') })) {
        jar.clear(BASE);
        return;
    }

    const csrf = token(page.body);
    const form = fields(page.body, n);
    const headers = { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' };

    // الإجابة على مهل، بحفظ تلقائي كما يفعل المتصفح
    let elapsed = 0;
    while (elapsed < EXAM_SECONDS) {
        const pause = 30 + Math.random() * 30;
        sleep(pause);
        elapsed += pause;

        const autosave = http.post(`${BASE}/competitions/${SLUG}/entry/autosave`, form, { headers, tags: { name: 'autosave' } });
        check(autosave, { 'autosaved': (r) => r.status === 200, 'no server error': serverOk });
    }

    const submit = http.post(`${BASE}/competitions/${SLUG}/entry/submit`, { _token: csrf, ...form }, { redirects: 0, tags: { name: 'submit' } });
    check(submit, { 'submit answered': (r) => r.status === 302, 'no server error': serverOk });

    const after = http.get(`${BASE}/competitions/${SLUG}/entry`, { tags: { step: 'page', name: 'entry-after' } });
    if (/dir="ltr">[A-HJ-NP-Z2-9]{10}</.test(after.body)) {
        confirmed.add(1);
    }

    jar.clear(BASE);
}
