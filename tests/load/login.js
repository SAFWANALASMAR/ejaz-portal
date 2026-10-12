// الدخول والخروج بحسابات الاختبار (ejaz:load-accounts): صفحة الدخول، ثم الدخول، ثم لوحة الحساب، ثم الخروج.
// يقيس فحص كلمة المرور (bcrypt) والجلسات في Redis، وهما الأثقل عند فتح التسجيل.
//
//   export LOAD_PASSWORD=...   # من مخرجات ejaz:load-accounts، لا يُكتب في الأمر
//   k6 run -e BASE_URL=https://staging.example -e PASSWORD="$LOAD_PASSWORD" -e ACCOUNTS=30000 \
//          -e PROFILE=spike -e PEAK=1000 tests/load/login.js

import http from 'k6/http';
import { check, sleep } from 'k6';
import exec from 'k6/execution';
import { ACCOUNTS, BASE, guard, login, serverOk, stages, token } from './lib.js';

guard('PASSWORD');

export const options = {
    scenarios: {
        sessions: { executor: 'ramping-vus', startVUs: 0, stages: stages(1000), gracefulRampDown: '30s' },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
        'http_req_duration{name:login}': ['p(95)<1500'],
        'http_req_duration{name:dashboard}': ['p(95)<1500'],
    },
};

export default function () {
    const n = (exec.scenario.iterationInTest % ACCOUNTS) + 1;
    const jar = http.cookieJar();

    if (!login(n)) {
        jar.clear(BASE);
        return;
    }

    const dashboard = http.get(`${BASE}/account`, { tags: { name: 'dashboard' } });
    check(dashboard, { 'dashboard 200': (r) => r.status === 200, 'no server error': serverOk });
    sleep(5 + Math.random() * 10);

    const logout = http.post(`${BASE}/logout`, { _token: token(dashboard.body) }, { redirects: 0, tags: { name: 'logout' } });
    check(logout, { 'logged out': (r) => r.status === 302 });

    jar.clear(BASE);
}
