// تمهيد اختبارات المتصفح:
// ١. تحويل localhost:8000 إلى حاوية التطبيق، وlocalhost:5173 إلى خادم Vite،
//    لأن المتصفح يعمل في حاوية مستقلة على شبكة Docker.
// ٢. تسجيل دخول المستخدم التجريبي مرة واحدة وحفظ الجلسة، حتى لا يتكرر الدخول
//    فيصطدم بحد المحاولات.
import net from 'node:net';
import { chromium } from '@playwright/test';

export const AUTH_STATE = 'storage/playwright/demo-user.json';

function forward(localPort, host, port) {
    const server = net.createServer((client) => {
        const upstream = net.connect(port, host);
        client.pipe(upstream);
        upstream.pipe(client);
        client.on('error', () => upstream.destroy());
        upstream.on('error', () => client.destroy());
    });

    return new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(localPort, '127.0.0.1', () => resolve(server));
    });
}

export default async function globalSetup(config) {
    // منفذ البوابة على الجهاز (APP_PORT في .env، وتمرره خدمة browser)، لأن روابط التطبيق تُبنى من APP_URL
    // وخارج حاويات المشروع (فحص الوصول على خادم محلي): APP_HOST=host.docker.internal وAPP_UPSTREAM_PORT
    // لمنفذه، وVITE_HOST=none مع الأصول المبنية (npm run build) فلا خادم Vite
    const servers = [await forward(Number(process.env.APP_PORT ?? 8000), process.env.APP_HOST ?? 'app', Number(process.env.APP_UPSTREAM_PORT ?? 8000))];

    if (process.env.VITE_HOST !== 'none') {
        servers.push(await forward(5173, process.env.VITE_HOST ?? 'vite', 5173));

        // تسخين خادم Vite: أول تجميع للأنماط بطيء عبر قرص ويندوز المشترك
        for (const asset of ['@vite/client', 'resources/css/app.css', 'resources/js/app.js']) {
            await fetch(`http://localhost:5173/${asset}`, { signal: AbortSignal.timeout(180_000) });
        }
    }

    // البيانات كما طبعها ejaz:demo-user --json (كلمة المرور قد تحوي رموزاً يهربها JSON، فلا تُستخرج بـ sed)
    const credentials = process.env.DEMO_CREDENTIALS ? JSON.parse(process.env.DEMO_CREDENTIALS) : {};
    const DEMO_EMAIL = process.env.DEMO_EMAIL ?? credentials.email;
    const DEMO_PASSWORD = process.env.DEMO_PASSWORD ?? credentials.password;

    if (DEMO_EMAIL && DEMO_PASSWORD) {
        const browser = await chromium.launch();
        const page = await browser.newPage({ baseURL: config.projects[0].use.baseURL });

        await page.goto('/login');
        await page.fill('#field-login', DEMO_EMAIL);
        await page.fill('#field-password', DEMO_PASSWORD);
        await Promise.all([page.waitForURL('**/account'), page.click('form button[type=submit]')]);
        await page.context().storageState({ path: AUTH_STATE });
        await browser.close();
    }

    return async () => servers.forEach((server) => server.close());
}
