<?php

use App\Actions\Assistant\SaveAssistantSettings;
use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Exceptions\OnlineMeetingFailed;
use App\Filament\Resources\NewsArticles\Pages\CreateNewsArticle;
use App\Http\Middleware\RejectInvalidUtf8;
use App\Models\Competition;
use App\Models\Meeting;
use App\Models\MessagingChannel;
use App\Models\NewsArticle;
use App\Models\Opening;
use App\Models\Page;
use App\Models\ServiceRequest;
use App\Models\SubmissionWork;
use App\Models\User;
use App\Services\BotChallenge\TurnstileChallenge;
use App\Services\Messaging\ProviderHttp;
use App\Services\OnlineMeetings\GraphOnlineMeetings;
use App\Support\Assistant\CurrentPage;
use App\Support\TeamsConnection;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;

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
it('refuses invalid UTF-8 in a field or a file name with 400, before it reaches the database or the session', function () {
    $this->post('/register', ['name' => "\xC3\x28abc", 'email' => 'utf8@example.test', 'password' => 'Utf8-Probe-Pass-2026', 'password_confirmation' => 'Utf8-Probe-Pass-2026'])
        ->assertStatus(400);
    expect(User::query()->where('email', 'utf8@example.test')->exists())->toBeFalse();

    // والاستعلام في الطلب غير GET كالحقل: لارافيل يدمجه في المدخل (ملاحظة المشرف على #61)
    $this->post('/register?name=%C3%28abc', ['email' => 'utf8-query@example.test', 'password' => 'Utf8-Probe-Pass-2026', 'password_confirmation' => 'Utf8-Probe-Pass-2026'])
        ->assertStatus(400);
    expect(User::query()->where('email', 'utf8-query@example.test')->exists())->toBeFalse();

    $this->post('/contact', ['answers' => ['about' => ['nested' => "ok\xFF"]]])->assertStatus(400);
    $this->post('/contact', ['file' => UploadedFile::fake()->create("cv\xFF.pdf", 1, 'application/pdf')])->assertStatus(400);
});

// الجلسة تُحفظ بصيغة JSON: لو بلغها المدخل المكسور في «المدخل القديم» بعد خطأ تحقق لفرغت. فالرد 400 لا يلمس
// الجلسة (لا كعكة جلسة فيه)، وطلب تالٍ بكعكة الدخول نفسها ما زال مسجلاً: يُدخل الحساب بطلب حقيقي، والجلسة في ملف،
// ويُنسى الحارس ومدير الجلسات بين الطلبات، فلا يبقى مسجلاً إلا بجلسته المحفوظة.
// وفقدان الجلسة بلا الوسيط لا يظهر في بيئة الاختبار، فدليله الحي في تقرير الأمن (م١)
it('keeps a signed-in member signed in after a refused broken field or query', function (string $query, array $data) {
    config(['session.driver' => 'file']);
    app('session')->forgetDrivers();
    $user = User::factory()->create(['password' => 'Keep-Session-Pass-2026']);
    $opening = Opening::factory()->create();
    $cookie = config('session.cookie');
    $session = $this->post('/login', ['login' => $user->email, 'password' => 'Keep-Session-Pass-2026'])->getCookie($cookie, false)->getValue();

    app('auth')->forgetGuards();
    app('session')->forgetDrivers();
    $refused = $this->withUnencryptedCookie($cookie, $session)->from(route('services.apply', $opening->slug))
        ->post(route('services.apply.store', $opening->slug).$query, $data)
        ->assertStatus(400);
    expect($refused->getCookie($cookie, false))->toBeNull();

    app('auth')->forgetGuards();
    app('session')->forgetDrivers();
    $this->withUnencryptedCookie($cookie, $session)->get('/account')->assertOk();
    $this->assertAuthenticatedAs($user);
})->with([
    'broken field' => ['', ['intent' => 'save', 'answers' => ['about' => "رسالة\xFF"]]],
    'broken query' => ['?x=%FF', ['intent' => 'save', 'answers' => ['about' => 'نبذة']]],
]);

it('keeps valid Arabic fields and an Arabic file name as they are', function () {
    $member = userWithRole(RoleName::Member);
    $opening = Opening::factory()->create();
    $about = 'نبذة عربية — بالتشكيل: مُحَمَّد، والأرقام ١٢٣';

    $this->actingAs($member)->post(route('services.apply.store', $opening->slug), [
        'intent' => 'save',
        'answers' => ['about' => $about],
        'files' => ['cv' => UploadedFile::fake()->createWithContent('السيرة الذاتية.pdf', pdfContents())],
    ])->assertRedirect();

    $saved = ServiceRequest::query()->where('user_id', $member->id)->sole();
    expect($saved->answers['about'])->toBe($about)
        ->and($saved->files['cv']['name'])->toBe('السيرة الذاتية.pdf');
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

// وما يبقى في failed_jobs من نص الاستثناء يُحذف بعد أسبوع، كقائمة Horizon
it('prunes failed jobs after a week, daily', function () {
    $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains((string) $event->command, 'queue:prune-failed'));

    expect($events)->toHaveCount(1)
        ->and((string) $events->first()->command)->toContain('--hours=168')
        ->and($events->first()->expression)->toBe('0 0 * * *');
});

// وبخطأ حقيقي من PostgreSQL (قيد فريد): نص الخطأ للتشخيص بلا سطر DETAIL، ورسالة الاستثناء نفسها بلا القيم المربوطة
// (mask_bindings_in_exception_messages)، فلا تصل إلى failed_jobs ولا Horizon ولا الاستثناء المغلّف في قالب
// ونص PostgreSQL نفسه قد يحمل قيمة المدخل (22P02: invalid input syntax for type uuid: "…")، فيُسجَّل رمز الخطأ وحده
// (بقايا مراجعة #61)
it('logs only the SQLSTATE of a real PostgreSQL error, and masks the bound values in the exception itself', function (string $case, string $state, string $secret) {
    User::factory()->create(['email' => 'dupe@example.test']);
    $logged = [];
    Log::listen(function (MessageLogged $message) use (&$logged): void {
        $logged[] = $message->message.' '.json_encode($message->context, JSON_UNESCAPED_UNICODE);
    });

    try {
        match ($case) {
            'unique' => DB::table('users')->insert(['name' => 'x', 'email' => 'dupe@example.test', 'password' => 'secret-hash-value', 'created_at' => now(), 'updated_at' => now()]),
            'uuid' => DB::select('select * from service_requests where uuid = ?::uuid', ['secret-typed-value']),
        };
        $this->fail('the query did not fail');
    } catch (QueryException $exception) {
        report($exception);
        expect($exception->getMessage())->not->toContain('secret-hash-value');
    }

    expect(implode("\n", $logged))->toContain($state)->toContain('Database query failed')
        ->not->toContain($secret)->not->toContain('secret-hash-value')->not->toContain('invalid input syntax')->not->toContain('duplicate key');
})->with([
    'unique violation' => ['unique', '23505', 'dupe@example.test'],
    'invalid uuid' => ['uuid', '22P02', 'secret-typed-value'],
]);

// ب٤: SSRF عبر عنوان مزود المساعد. الحماية قائمة مسموحة بالمضيف الدقيق من البيئة (AI_ASSISTANT_ALLOWED_HOSTS)، لا فحص
// للعناوين الخاصة: فكل مضيف خارجها مرفوض أياً كانت صيغته (عشرية، أو ثمانية، أو ست عشرية، أو IPv6 معيّنة، أو اسم خارجي
// يُحل إلى عنوان داخلي)، ولا يُحل اسم المضيف المسموح إلا إلى مزوده، فلا محل لإعادة ربط DNS. واختبار الاتصال لا يرسل
// المفتاح المحفوظ إلى مضيف آخر: AssistantAdminTest («tests the connection with the stored key only for the provider and
// host it was saved for» و«asks for the key again before sending a saved key to another provider or host»)
it('accepts only the exact provider hosts allowed on the server, whatever form an internal address takes', function (string $url) {
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
    'octal ip' => 'http://0177.0.0.1/v1',
    'hex ip' => 'http://0x7f000001/v1',
    'ipv6 loopback' => 'http://[::1]/v1',
    'ipv4-mapped ipv6' => 'http://[::ffff:169.254.169.254]/v1',
    'outside name resolving inside' => 'https://127.0.0.1.nip.io/v1',
    'allowed host as user part' => 'https://api.openai.com@127.0.0.1/v1',
    'allowed host as subdomain' => 'https://api.openai.com.evil.example/v1',
]);

// والاستعلام وUser-Agent في GET لا يفحصهما الوسيط: فما يكتب في GET لا يتعطل بهما. عدّاد الزيارات يأخذ بصمة الوكيل،
// والجلسة تحفظ الرابط السابق (_previous.url) مرمَّزاً، فتبقى الجلسة بعد طلب كهذا (بقايا مراجعة #61)
it('keeps GET pages, the visit counter and the session working with a broken query and user agent', function () {
    config(['session.driver' => 'file']);
    app('session')->forgetDrivers();
    $broken = ['User-Agent' => "Mozilla/5.0 \xFF\xC3\x28"];

    foreach (['/?utm=%FF', '/media/news?q=%FF', '/library?q=%C3%28', '/programs?type=%FF', '/contact?x=%FF'] as $uri) {
        expect($this->withHeaders($broken)->get($uri)->getStatusCode())->toBeIn([200, 302], $uri);
    }

    $user = User::factory()->create(['password' => 'Keep-Session-Pass-2026']);
    $cookie = config('session.cookie');
    $session = $this->flushHeaders()->post('/login', ['login' => $user->email, 'password' => 'Keep-Session-Pass-2026'])->getCookie($cookie, false)->getValue();
    app('auth')->forgetGuards();
    app('session')->forgetDrivers();
    $this->withHeaders($broken)->withUnencryptedCookie($cookie, $session)->get('/account?x=%FF&y=%C3%28')->assertOk();
    app('auth')->forgetGuards();
    app('session')->forgetDrivers();
    $this->flushHeaders()->withUnencryptedCookie($cookie, $session)->get('/account')->assertOk();
});

// أنماط مضمّنة (style="") في قوالب اللوحة المضافة (recent-logs وstats-analysis) تمر من CSP في الإنتاج: style-src فيه
// 'unsafe-inline' بلا nonce (فالمتصفح يهمل 'unsafe-inline' متى وُجد nonce)، في اللوحة والموقع (بقايا مراجعة #61)
it('lets inline styles through the production CSP, with no style nonce that would cancel unsafe-inline', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    app()->detectEnvironment(fn () => 'production');

    foreach ([
        [userWithRole(RoleName::SuperAdmin), '/admin/messaging-channels'],
        [null, '/'],
    ] as [$user, $uri]) {
        $response = $user ? $this->actingAs($user)->get($uri) : $this->get($uri);
        preg_match('/style-src ([^;]+)/', (string) $response->headers->get('Content-Security-Policy'), $style);

        expect($response->getStatusCode())->toBe(200, $uri)
            ->and($style[1] ?? '')->toContain("'unsafe-inline'")->not->toContain('nonce-');
    }
});

// المسار المكسور الترميز يرفضه الإطار نفسه بـ 400 قبل أي استعلام (/pages/%C3%28)، فيبقى كذلك
it('answers 400 to a path segment that is not valid UTF-8', function (string $path) {
    $this->get($path)->assertStatus(400);
})->with(['/pages/%C3%28', '/governance/%C3%28', '/media/news/%C3%28', '/programs/%C3%28', '/services/openings/%C3%28']);

it('never follows a redirect from a messaging provider, so a key in the body is not sent on', function () {
    expect(ProviderHttp::request()->getOptions()['allow_redirects'] ?? null)->toBeFalse();
});

// ومثله Microsoft Graph وخادم دخوله: تحويل 307 لا يُتبع، فلا يذهب السر ولا الرمز إلى مضيف آخر (مراجعة #61)
it('never follows a redirect from Microsoft, so the secret and the token stay with it', function (Closure $fake) {
    config(['services.online_meetings.driver' => 'graph']);
    Http::preventStrayRequests();
    Http::fake([...$fake(), 'evil.example/*' => Http::response(['access_token' => 'stolen', 'expires_in' => 3600])]);
    $graph = new GraphOnlineMeetings(new TeamsConnection('tenant-1', 'client-1', 'teams-secret-value', 'organizer@ejaz.example'));

    expect(fn () => $graph->create(Meeting::factory()->online()->create()))->toThrow(OnlineMeetingFailed::class);
    Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'evil.example'));
})->with([
    'login redirect' => [fn () => ['login.microsoftonline.com/*' => Http::response('', 307, ['Location' => 'https://evil.example/token'])]],
    'Graph redirect' => [fn () => [
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'graph-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response('', 307, ['Location' => 'https://evil.example/events']),
    ]],
]);

// ب٥ (عالية، من مراجعة #61): User-Agent بترميز غير صالح كان يُسقط ترميز سجل الأمن قبل أن يحسب Fortify المحاولة،
// فيرد 500 بلا قفل ولا سجل، فيُخمَّن كلمة المرور بلا حد. والإصلاح في المستمع نفسه، فيُختبر بلا الوسيط
it('still locks the account and records each failure when the user agent is not valid UTF-8', function () {
    $this->withoutMiddleware(RejectInvalidUtf8::class);
    $user = User::factory()->create(['password' => 'Right-Pass-2026-xyz']);

    foreach (range(1, 5) as $attempt) {
        $this->withHeader('User-Agent', "x\xFF")->post('/login', ['login' => $user->email, 'password' => 'wrong'.$attempt])
            ->assertRedirect()->assertSessionHasErrors();
    }
    $this->withHeader('User-Agent', "x\xFF")->post('/login', ['login' => $user->email, 'password' => 'Right-Pass-2026-xyz'])
        ->assertSessionHasErrors();
    $this->assertGuest();

    $failures = Activity::query()->where('log_name', 'security')->where('event', ActivityEvent::LoginFailed->value)->get();
    expect($failures)->toHaveCount(5)
        ->and(mb_check_encoding((string) $failures->first()->properties['agent'], 'UTF-8'))->toBeTrue();
});

it('refuses a non-UTF-8 user agent on a form post, and lets it read a page', function () {
    $this->withHeader('User-Agent', "x\xFF")->post('/login', ['login' => 'a@example.test', 'password' => 'x'])->assertStatus(400);
    $this->withHeader('User-Agent', "x\xFF")->get('/login')->assertOk();
});

// ب٥: رمز التحقق الثنائي جُرّب فعلاً لا استنتاجاً من الإعدادات: الرمز الصحيح لا يُقبل مرتين في نافذته، والتخمين يُحجب
// بعد خمس محاولات للحساب نفسه، ولا يُلتف عليه ببدء الدخول من جديد (المفتاح بالحساب لا بالجلسة)
it('refuses a two-factor code used once already, and blocks guessing per account across new sign-ins', function () {
    $user = User::factory()->withTwoFactor()->create(['email' => 'totp@example.test']);
    $code = (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP');
    $signIn = fn () => $this->post('/login', ['login' => 'totp@example.test', 'password' => 'password'])->assertRedirect('/two-factor-challenge');

    $signIn();
    $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect();
    $this->assertAuthenticatedAs($user);
    $this->post('/logout');

    $signIn();
    $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect('/two-factor-challenge')->assertSessionHasErrors('code');
    $this->assertGuest();

    $other = User::factory()->withTwoFactor()->create(['email' => 'guess@example.test']);
    $wrong = $code === '000000' ? '111111' : '000000';
    $this->post('/login', ['login' => 'guess@example.test', 'password' => 'password']);
    foreach (range(1, 4) as $attempt) {
        $this->post('/two-factor-challenge', ['code' => $wrong])->assertSessionHasErrors('code');
    }
    $this->post('/login', ['login' => 'guess@example.test', 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => $wrong])->assertSessionHasErrors('code');
    $this->post('/two-factor-challenge', ['code' => $wrong])->assertStatus(429);
    expect($other->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

// ب٦: رفع صور المسابقات وصور اللوحة: SVG وHTML بامتداد صورة، وامتداد مزدوج. والصورة السليمة باسم .php تُحفظ باسم
// عشوائي بامتداد صورة، لا بامتدادها
it('refuses SVG and HTML as competition images, and never keeps a double extension', function () {
    Storage::fake('local');
    $competition = Competition::factory()->withQuestions()->create(['slug' => 'works-audit', 'work_types' => ['image', 'pdf']]);
    $this->actingAs(User::factory()->create(['phone' => '+966500000002', 'phone_verified_at' => now()]))->post('/competitions/works-audit/entry/start');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>';

    foreach ([
        UploadedFile::fake()->createWithContent('w.svg', $svg),
        UploadedFile::fake()->createWithContent('w.png', $svg),
        UploadedFile::fake()->createWithContent('w.jpg', '<html><script>alert(1)</script></html>'),
        UploadedFile::fake()->createWithContent('w.png.php', '<?php system($_GET["c"]); ?>'),
    ] as $file) {
        $this->post('/competitions/works-audit/entry/works', ['type' => 'image', 'file' => $file])->assertSessionHasErrors('file');
    }
    $this->post('/competitions/works-audit/entry/works', ['type' => 'pdf', 'file' => UploadedFile::fake()->createWithContent('w.pdf', '<html><script>alert(1)</script></html>')])->assertSessionHasErrors('file');
    expect(SubmissionWork::count())->toBe(0);

    $this->post('/competitions/works-audit/entry/works', ['type' => 'image', 'file' => UploadedFile::fake()->image('w.php.png', 20, 20)])->assertSessionHasNoErrors();
    $this->post('/competitions/works-audit/entry/works', ['type' => 'image', 'file' => UploadedFile::fake()->createWithContent('w.png.php', imageBytes('png', 20, 20))]);
    expect(SubmissionWork::query()->pluck('path')->all())->each->toMatch('/^competition-works\/[^\/.]+\.(png|jpe?g|webp)$/');
});

it('refuses SVG, HTML and a double extension as a news cover in the panel', function (UploadedFile $file) {
    Storage::fake('public');
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(CreateNewsArticle::class)
        ->fillForm(['title' => 'خبر', 'slug' => 'audit-cover', 'published_on' => '2026-02-01', 'summary' => 'ملخص', 'image' => [$file], 'is_published' => false])
        ->call('create')
        ->assertHasFormErrors(['image']);

    expect(NewsArticle::query()->where('slug', 'audit-cover')->exists())->toBeFalse();
})->with([
    'svg' => fn () => UploadedFile::fake()->createWithContent('cover.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'),
    'svg named jpg' => fn () => UploadedFile::fake()->createWithContent('cover.jpg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'),
    'html named jpg' => fn () => UploadedFile::fake()->createWithContent('cover.jpg', '<html><script>alert(1)</script></html>'),
    'php double extension' => fn () => UploadedFile::fake()->createWithContent('cover.jpg.php', '<?php system($_GET["c"]); ?>'),
]);

// ب٨: إعادة رمز Turnstile صالح: يُرفض محلياً قبل سؤال Cloudflare، ويرفضه Cloudflare أيضاً (timeout-or-duplicate)
it('refuses a Turnstile token used once already, here and at Cloudflare', function () {
    $turnstile = new TurnstileChallenge('site', 'secret', 'ejaz.sa');
    Http::fake(['challenges.cloudflare.com/*' => Http::sequence()
        ->push(['success' => true, 'action' => 'survey', 'hostname' => 'ejaz.sa'])
        ->push(['success' => false, 'error-codes' => ['timeout-or-duplicate']]),
    ]);

    expect($turnstile->passes('solved-once', 'survey'))->toBeTrue()
        ->and($turnstile->passes('solved-once', 'survey'))->toBeFalse();
    Http::assertSentCount(1);

    // ولو قبله Cloudflare مرة ثانية (رمز جديد)، فالرد الثاني منه للرفض يُحترم
    Cache::flush();
    expect($turnstile->passes('solved-once', 'survey'))->toBeFalse();
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
// والمرسل قالب المسار بلا قيمه، لا قائمة منع تفوتها أشكال أخرى (ULID، base64url بـ - و_)، بملاحظة المشرف على #61
it('sends the route template of the page to the assistant provider, never a value in its path', function (string $path, string $value, string $template) {
    $fake = enableAssistant();
    Page::factory()->create(['slug' => 'manuscripts', 'title' => 'معرض المخطوطات', 'summary' => 'تعرض الجمعية المخطوطات النادرة في معرضها السنوي.']);

    $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
        ->postJson(route('assistant.ask'), ['question' => 'أين المخطوطات؟', 'page' => $path])
        ->assertOk();

    expect($fake->requests)->toHaveCount(1)
        ->and($fake->requests[0]['message'])->toContain($template.'</page>')->not->toContain($value);
})->with([
    'reset token' => ['/reset-password/'.str_repeat('a1b2', 16), str_repeat('a1b2', 16), '/reset-password/{token}'],
    'reset token in base64url' => ['/reset-password/Zk9_x-Q2LmN8pR4tVw7yB1cD3fG5hJ6k', 'Zk9_x-Q2LmN8pR4tVw7yB1cD3fG5hJ6k', '/reset-password/{token}'],
    'request uuid' => ['/account/requests/9d3f1c2a-5b6e-4f70-8a91-0c2d3e4f5a6b', '9d3f1c2a', '/account/requests/{serviceRequest}'],
    'ULID' => ['/account/requests/01JAX3P7QKZ8V6T2M4N9R5W1YB', '01JAX3P7QKZ8V6T2M4N9R5W1YB', '/account/requests/{serviceRequest}'],
    'record number' => ['/assembly/meetings/42', '/42', '/assembly/meetings/{meeting}'],
    'public page slug' => ['/pages/manuscripts', '/pages/manuscripts', '/pages/{slug}'],
]);

it('names an ordinary page by its template', function () {
    expect(CurrentPage::template('/media/news'))->toBe('/media/news')
        ->and(CurrentPage::template('/pages/manuscripts'))->toBe('/pages/{slug}')
        ->and(CurrentPage::template('/no-such-page/at/all'))->toBeNull();
});
