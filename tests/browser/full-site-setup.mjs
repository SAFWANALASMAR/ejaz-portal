// تمهيد التجربة الشاملة داخل حاوية Playwright (docker compose run browser): يحوّل منفذ الموقع والبريد
// التجريبي على localhost إلى حاويتيهما، فتعمل روابط APP_URL وروابط البريد كما في متصفح صفوان.
// FULL_SITE_UPSTREAM=app:8000 وFULL_SITE_MAILPIT_UPSTREAM=mailpit:8025، وبدونهما لا شيء (التشغيل على الجهاز).
import net from 'node:net';

function forward(localPort, upstream) {
    const [host, port] = upstream.split(':');
    const server = net.createServer((client) => {
        const remote = net.connect(Number(port), host);
        client.pipe(remote);
        remote.pipe(client);
        client.on('error', () => remote.destroy());
        remote.on('error', () => client.destroy());
    });

    return new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(localPort, '127.0.0.1', () => resolve(server));
    });
}

export default async function fullSiteSetup(config) {
    const servers = [];
    if (process.env.FULL_SITE_UPSTREAM) servers.push(await forward(Number(new URL(config.projects[0].use.baseURL).port || 80), process.env.FULL_SITE_UPSTREAM));
    if (process.env.FULL_SITE_MAILPIT_UPSTREAM) servers.push(await forward(Number(new URL(process.env.MAILPIT_URL ?? 'http://localhost:8025').port), process.env.FULL_SITE_MAILPIT_UPSTREAM));

    return async () => servers.forEach((server) => server.close());
}
