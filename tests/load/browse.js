// تصفح الزوار بلا حساب (performance.md): الرئيسية ثم أقسام البوابة العامة، بفترات قراءة.
// يقيس الصفحات العامة المخزنة مؤقتاً، وهي أغلب الحركة وقت الذروة.
//
//   k6 run -e BASE_URL=https://staging.example -e PROFILE=peak -e PEAK=2000 tests/load/browse.js

import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE, guard, serverOk, stages } from './lib.js';

guard();

const SECTIONS = [
    '/about',
    '/initiatives',
    '/governance',
    '/library',
    '/media',
    '/media/news',
    '/programs',
    '/competitions',
    '/contact',
];

export const options = {
    scenarios: {
        visitors: { executor: 'ramping-vus', startVUs: 0, stages: stages(1000), gracefulRampDown: '30s' },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
        // performance.md: أقل من 300 ملي ثانية في الحمل العادي، و1.5 ثانية حداً أعلى تحت الذروة
        'http_req_duration{name:home}': ['p(95)<1500'],
        'http_req_duration{name:section}': ['p(95)<1500'],
    },
};

export default function () {
    const home = http.get(`${BASE}/`, { tags: { name: 'home' } });
    check(home, { 'home 200': (r) => r.status === 200, 'no server error': serverOk });
    sleep(2 + Math.random() * 4);

    // ثلاثة أقسام عشوائية، ثم أول رابط داخلي من آخرها (عنصر أو خبر)
    let last = home;
    for (let i = 0; i < 3; i++) {
        const path = SECTIONS[Math.floor(Math.random() * SECTIONS.length)];
        last = http.get(`${BASE}${path}`, { tags: { name: 'section' } });
        check(last, { 'section 200': (r) => r.status === 200, 'no server error': serverOk });
        sleep(3 + Math.random() * 7);
    }

    const links = [...String(last.body || '').matchAll(/href="(?:https?:\/\/[^"/]+)?(\/(?:library|media\/news|initiatives|governance|programs|competitions)\/[a-z0-9-]+(?:\/[a-z0-9-]+)?)"/g)];

    if (links.length > 0) {
        const detail = http.get(`${BASE}${links[Math.floor(Math.random() * links.length)][1]}`, { tags: { name: 'detail' } });
        check(detail, { 'detail ok': (r) => r.status === 200, 'no server error': serverOk });
        sleep(5 + Math.random() * 10);
    }
}
