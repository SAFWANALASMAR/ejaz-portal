// البريد التجريبي (Mailpit على MAILPIT_URL، الافتراضي http://localhost:8025): رمز توثيق البريد بعد التسجيل
// (decisions/2026-11-06-email-otp.md)، وإدخاله في صفحة التوثيق. الرسالة تُرسل في الطابور، فيُنتظر وصولها.
export const MAILPIT = process.env.MAILPIT_URL ?? 'http://localhost:8025';

// أحدث رمز من ستة أرقام في رسالة «رمز توثيق بريدك» إلى العنوان
export async function latestEmailCode(request, to) {
    for (let attempt = 0; attempt < 30; attempt++) {
        const search = await (await request.get(`${MAILPIT}/api/v1/search?query=${encodeURIComponent(`to:"${to}"`)}`)).json();
        for (const message of search.messages ?? []) {
            if (!(message.Subject ?? '').includes('رمز توثيق')) continue;
            const full = await (await request.get(`${MAILPIT}/api/v1/message/${message.ID}`)).json();
            const body = `${full.Text ?? ''} ${full.HTML ?? ''}`;
            const code = body.match(/\*\*(\d{6})\*\*|<strong>\s*(\d{6})\s*<\/strong>/) ?? body.match(/\b(\d{6})\b/);
            if (code) return code[1] ?? code[2];
        }
        await new Promise((resolve) => setTimeout(resolve, 1000));
    }
    throw new Error(`لا رمز توثيق في بريد ${to}`);
}

// على صفحة التوثيق (/email/verify): يجلب الرمز ويرسله، وينتظر مغادرة الصفحة
export async function verifyEmailCode(page, request, email) {
    const form = page.locator('main form[action$="/account/verify-email"]');
    await form.locator('[name=code]').fill(await latestEmailCode(request, email));
    await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/email/verify')), form.locator('button').first().click()]);
}
