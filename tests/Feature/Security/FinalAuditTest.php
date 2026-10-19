<?php

use App\Actions\Assistant\SaveAssistantSettings;
use App\Enums\RoleName;
use App\Models\MessagingChannel;
use App\Models\Page;
use App\Models\User;
use App\Services\Messaging\ProviderHttp;
use App\Support\Assistant\CurrentPage;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/*
| الاختبارات المانعة لعودة ما وُجد في اختبار الأمن قبل الرفع (docs/security/2026-10-19-اختبار-الأمن.md).
*/

// ب٢: معامل مصفوفة في رابط عام كان يُسقط الصفحة بخطأ 500 (Array to string conversion)
it('treats an array program type as an unknown type, not a server error', function () {
    $this->get('/programs?type[]=1')->assertRedirect(route('programs.index'));
});

it('refuses an array verify token on the WhatsApp subscription check, not a server error', function () {
    MessagingChannel::query()->create([
        'channel' => MessagingChannel::WHATSAPP,
        'provider' => 'cloud',
        'options' => ['phone_number_id' => '1234567890'],
        'secrets' => ['access_token' => 'wa-token', 'app_secret' => 'meta-app-secret', 'verify_token' => 'verify-me'],
    ]);

    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token[]=verify-me&hub.challenge=1')->assertForbidden();
    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge[]=1')->assertOk()->assertContent('');
});

// ب٢: مدخل بترميز UTF-8 غير صالح كان يصير 500 من PostgreSQL، ويُخرج صاحبه من حسابه بعد خطأ تحقق
it('refuses invalid UTF-8 in a field, a query or a file name with 400, before it reaches the database or the session', function () {
    $this->post('/register', ['name' => "\xC3\x28abc", 'email' => 'utf8@example.test', 'password' => 'Utf8-Probe-Pass-2026', 'password_confirmation' => 'Utf8-Probe-Pass-2026'])
        ->assertStatus(400);
    $this->get("/library?q=\xC3\x28")->assertStatus(400);
    expect(User::query()->where('email', 'utf8@example.test')->exists())->toBeFalse();

    $member = User::factory()->create();
    $this->actingAs($member)->post('/contact', ['answers' => ['about' => ['nested' => "ok\xFF"]]])->assertStatus(400);
    $this->actingAs($member)->post('/contact', ['file' => UploadedFile::fake()->create("cv\xFF.pdf", 1, 'application/pdf')])->assertStatus(400);
    $this->assertAuthenticatedAs($member);
});

it('keeps valid Arabic input as it is', function () {
    $this->get('/library?q='.urlencode('المخطوطات'))->assertOk();
});

// ب١٠: خطأ القاعدة كان يُسجَّل بالقيم المربوطة: الاسم والبريد والجوال وبصمة كلمة المرور
it('logs a database error without the bound values or the duplicate key detail', function () {
    $logged = [];
    Log::listen(function (MessageLogged $message) use (&$logged): void {
        $logged[] = $message->message.' '.json_encode($message->context);
    });

    report(new QueryException('pgsql', 'insert into "users" ("email", "phone", "password") values (?, ?, ?)', ['victim@example.test', '+966500000999', '$2y$12$hash'],
        new PDOException('SQLSTATE[23505]: Unique violation: DETAIL: Key (email)=(victim@example.test) already exists.')));

    $all = implode("\n", $logged);
    expect($all)->toContain('Database query failed')->toContain('insert into')
        ->not->toContain('victim@example.test')->not->toContain('+966500000999')->not->toContain('$2y$12$hash');
});

// ب٤: SSRF عبر عنوان مزود المساعد: لا عنوان داخلي ولا شبكة خاصة ولا خدمة بيانات السحابة، ولو بصيغة ملتوية
it('refuses an internal or private provider address for the assistant', function (string $url) {
    config(['assistant.allowed_hosts' => ['api.anthropic.com', 'api.openai.com']]);
    $admin = userWithRole(RoleName::SuperAdmin);

    expect(fn () => app(SaveAssistantSettings::class)->handle($admin, ['provider' => 'openai_compatible', 'model' => 'm', 'base_url' => $url, 'api_key' => 'sk-x']))
        ->toThrow(ValidationException::class);
})->with([
    'loopback' => 'http://127.0.0.1:8000/v1',
    'localhost' => 'https://localhost/v1',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data',
    'private network' => 'https://10.0.0.5/v1',
    'docker service' => 'http://redis:6379',
    'decimal ip' => 'http://2130706433/v1',
    'ipv6 loopback' => 'http://[::1]/v1',
    'allowed host as user part' => 'https://api.openai.com@127.0.0.1/v1',
    'allowed host as subdomain' => 'https://api.openai.com.evil.example/v1',
]);

it('never follows a redirect from a messaging provider, so a key in the body is not sent on', function () {
    expect(ProviderHttp::request()->getOptions()['allow_redirects'] ?? null)->toBeFalse();
});

// ب٥: لا تثبيت للجلسة: معرّف جديد عند الدخول
it('starts a new session id on sign in', function () {
    $user = User::factory()->create(['email' => 'fixation@example.test', 'password' => 'Fixation-Pass-2026']);

    $this->get('/login')->assertOk();
    $before = session()->getId();
    $this->post('/login', ['login' => $user->email, 'password' => 'Fixation-Pass-2026'])->assertRedirect();

    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($before);
});

// ب٣: مسار الصفحة يصل إلى مزود المساعد، فلا يحمل رمز إعادة كلمة المرور ولا معرّف سجل
it('never sends a reset token, a record uuid or a record number in the page path to the assistant provider', function (string $path) {
    $fake = enableAssistant();
    Page::factory()->create(['slug' => 'manuscripts', 'title' => 'معرض المخطوطات', 'summary' => 'تعرض الجمعية المخطوطات النادرة في معرضها السنوي.']);

    $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
        ->postJson(route('assistant.ask'), ['question' => 'أين المخطوطات؟', 'page' => $path])
        ->assertOk();

    expect($fake->requests)->toHaveCount(1)
        ->and($fake->requests[0]['message'])->not->toContain('<page>')
        ->and(CurrentPage::path($path))->toBeNull();
})->with([
    'reset token' => '/reset-password/'.str_repeat('a1b2', 16),
    'request uuid' => '/account/requests/9d3f1c2a-5b6e-4f70-8a91-0c2d3e4f5a6b',
    'record number' => '/assembly/meetings/42',
]);

it('still sends an ordinary page path', function () {
    expect(CurrentPage::path('/media/news'))->toBe('/media/news')
        ->and(CurrentPage::path('/pages/manuscripts'))->toBe('/pages/manuscripts');
});
