<?php

use App\Enums\RoleName;
use App\Models\Document;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\User;
use App\Support\SpaLink;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\Support\Str;
use Illuminate\View\View as RenderedView;
use Livewire\Livewire;

// التنقّل السريع في الواجهة العامة (wire:navigate — docs/decisions.md)

/**
 * روابط الصفحة: الرابط وهل يحمل wire:navigate.
 *
 * @return list<array{href: string, navigate: bool, html: string}>
 */
function pageLinks(string $html): array
{
    preg_match_all('/<a\s[^>]*>/', $html, $matches);

    return array_map(fn (string $tag): array => [
        'href' => preg_match('/href="([^"]*)"/', $tag, $href) === 1 ? html_entity_decode($href[1]) : '',
        'navigate' => preg_match('/\swire:navigate[\s>]/', $tag) === 1,
        'html' => $tag,
    ], $matches[0]);
}

/**
 * @param  list<array{href: string, navigate: bool, html: string}>  $links
 */
function linkTo(array $links, string $href): array
{
    $found = array_values(array_filter($links, fn (array $link): bool => $link['href'] === $href));

    expect($found)->not->toBeEmpty("لا رابط إلى {$href}");

    return $found;
}

/**
 * سطر script-src من سياسة أمان المحتوى.
 */
function scriptSources(string $csp): string
{
    return collect(explode(';', $csp))->map(fn (string $directive): string => trim($directive))
        ->first(fn (string $directive): bool => str_starts_with($directive, 'script-src '), '');
}

/**
 * طلب تنقّل كما يرسله Livewire عند النقر على رابط wire:navigate.
 */
function navigateRequest(): array
{
    return ['X-Livewire-Navigate' => '1'];
}

beforeEach(function () {
    Storage::fake('local');

    MenuItem::factory()->create(['label' => 'الحوكمة', 'url' => '/governance', 'sort' => 1]);
    $media = MenuItem::factory()->create(['label' => 'المركز الإعلامي', 'url' => '/media', 'sort' => 2]);
    MenuItem::factory()->create(['parent_id' => $media->id, 'label' => 'الأخبار', 'url' => '/media/news']);
    MenuItem::factory()->create(['label' => 'المتجر', 'url' => '/store', 'opens_in_new_tab' => true, 'sort' => 3]);
    MenuItem::factory()->create(['label' => 'تواصل معنا', 'url' => '/contact', 'sort' => 4]);
});

it('marks the internal links in the header, mobile menu, footer and home cards for quick navigation', function () {
    $links = pageLinks($this->get('/')->assertOk()->getContent());

    // الشعار في الرأس والتذييل، وعناصر القائمة في الرأس وقائمة الجوال والتذييل، والعنصر الفرعي
    foreach ([url('/'), url('/governance'), url('/media'), url('/media/news'), url('/contact')] as $href) {
        foreach (linkTo($links, $href) as $link) {
            expect($link['navigate'])->toBeTrue($link['html']);
        }
    }

    // نسختا الرابط الفرعي على الأقل: القائمة المنسدلة وقائمة الجوال
    expect(count(linkTo($links, url('/media/news'))))->toBeGreaterThanOrEqual(2);
});

it('leaves login, new-tab, outside and download links to a full page load', function () {
    Setting::put('contact', ['social' => ['x' => 'https://x.com/example']]);

    $links = pageLinks($this->get('/')->assertOk()->getContent());

    foreach ([route('login'), url('/store'), 'https://x.com/example', '#main'] as $href) {
        foreach (linkTo($links, $href) as $link) {
            expect($link['navigate'])->toBeFalse($link['html']);
        }
    }
});

it('opens a governance document page quickly, and downloads and views its file with a full load', function () {
    $document = Document::factory()->create(['slug' => 'charter', 'title' => 'اللائحة الأساسية']);
    Storage::disk('local')->put($document->file, pdfContents());

    $index = pageLinks($this->get('/governance')->assertOk()->getContent());
    expect(linkTo($index, route('governance.show', 'charter'))[0]['navigate'])->toBeTrue();

    $show = pageLinks($this->get(route('governance.show', 'charter'))->assertOk()->getContent());
    expect(linkTo($show, route('governance.download', 'charter'))[0]['navigate'])->toBeFalse()
        ->and(linkTo($show, route('governance.file', 'charter'))[0]['navigate'])->toBeFalse();
});

it('decides which addresses are pages for quick navigation', function (string $url, bool $navigates) {
    expect(SpaLink::allows($url))->toBe($navigates);
})->with([
    'home' => [fn (): string => url('/'), true],
    'relative path' => ['/governance', true],
    'with a query' => [fn (): string => url('/governance?category=policies'), true],
    'account page' => [fn (): string => url('/account/requests'), true],
    'login' => [fn (): string => url('/login'), false],
    'register' => [fn (): string => url('/register'), false],
    'forgot password' => [fn (): string => url('/forgot-password'), false],
    'two-factor challenge' => [fn (): string => url('/two-factor-challenge'), false],
    'email verification' => [fn (): string => url('/email/verify'), false],
    'governance download' => [fn (): string => url('/governance/charter/download'), false],
    'governance file' => [fn (): string => url('/governance/charter/file'), false],
    'library file' => [fn (): string => url('/library/books/guide/file'), false],
    'signed link' => [fn (): string => url('/account/programs?expires=1&signature=abc'), false],
    'admin panel' => [fn (): string => url('/admin'), false],
    'store redirect' => [fn (): string => url('/store'), false],
    'donation' => [fn (): string => url('/donate'), false],
    'survey with its own policy' => [fn (): string => url('/surveys/abc'), false],
    'filled form, a standalone print document' => [fn (): string => url('/account/forms/0f4c2a9e-0000-4000-8000-000000000000'), false],
    'health check' => [fn (): string => url('/up'), false],
    'sitemap' => [fn (): string => url('/sitemap.xml'), false],
    'robots' => [fn (): string => url('/robots.txt'), false],
    'old site address' => [fn (): string => url('/2019/05/some-old-post'), false],
    'outside host' => ['https://example.com/governance', false],
    'protocol-relative' => ['//example.com/governance', false],
    'backslash host' => ['/\\example.com/governance', false],
    'mail' => ['mailto:info@ejaz.sa', false],
    'fragment' => ['#main', false],
    'empty' => ['', false],
]);

it('loads Livewire once in the head with the request nonce, and allows no eval or inline scripts on public pages', function () {
    $response = $this->get('/')->assertOk();
    $html = $response->getContent();
    $csp = (string) $response->headers->get('Content-Security-Policy');

    preg_match("/'nonce-([^']+)'/", $csp, $nonce);
    $head = strstr($html, '</head>', true);

    // سكربت Livewire ونمطه في الرأس برمز الطلب، والنمط هو ما يقرأ منه Livewire الرمز للصفحات التالية
    // والملف livewire.js في التطوير، وlivewire.min.js حين يُطفأ APP_DEBUG
    expect($head)->toMatch('#<script src="[^"]*/livewire(\.min)?\.js\?id=[^"]*" nonce="'.$nonce[1].'"#')
        ->toContain('<style nonce="'.$nonce[1].'" data-livewire-style')
        // مؤجل فلا يوقف عرض الصفحة، وتغيّر إصداره يعيد التحميل
        ->toMatch('#<script src="[^"]*/livewire(\.min)?\.js[^>]* defer="true" data-navigate-track="reload">#')
        ->and(preg_match_all('#/livewire(\.min)?\.js\?id=#', $html))->toBe(1)
        // شريط تقدّم Livewire معطّل (دوره في ARIA غير صالح)، وشريط الموقع بدله (page.js)
        ->and($html)->toContain('<body class="flex min-h-screen flex-col" data-no-progress-bar>');

    expect(scriptSources($csp))->toBe("script-src 'self' 'nonce-{$nonce[1]}'")
        ->not->toContain('unsafe-inline')
        ->not->toContain('unsafe-eval');
});

it('keeps eval for the Alpine expressions in the admin panel alone', function () {
    $csp = (string) $this->actingAs(userWithRole(RoleName::TechnicalAdmin))->get('/admin')->assertOk()
        ->headers->get('Content-Security-Policy');

    expect(scriptSources($csp))->toContain("'unsafe-eval'")
        ->not->toContain('unsafe-inline');
});

it('serves a page to a quick navigation as it is', function () {
    $this->get('/governance', navigateRequest())
        ->assertOk()
        ->assertSee('<h1', false)
        ->assertDontSee('http-equiv="refresh"', false);
});

it('turns a quick navigation that ends at the login page into a full page load', function () {
    $this->get('/account/requests', navigateRequest())
        ->assertOk()
        ->assertSee('<meta http-equiv="refresh" content="0;url='.route('login').'">', false)
        ->assertHeader('Cache-Control', 'no-store, private');

    // والطلب العادي تحويل كما كان
    $this->get('/account/requests')->assertRedirect(route('login'));
});

it('turns a quick navigation to a file into a full page load of the same address, without running its controller', function () {
    $document = Document::factory()->create(['slug' => 'charter']);

    // الملف ليس على القرص بعد: لو شُغّل المتحكم لردّ 404، فالرد صفحة التحميل الكامل دونه
    $this->get(route('governance.download', 'charter'), navigateRequest())
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=utf-8')
        ->assertHeader('Content-Security-Policy')
        ->assertSee('<meta http-equiv="refresh" content="0;url='.route('governance.download', 'charter').'">', false);

    Storage::disk('local')->put($document->file, pdfContents());
    $this->get(route('governance.download', 'charter'))->assertDownload();
});

it('loads the store and the outside store behind it fully', function () {
    $this->get('/store', navigateRequest())
        ->assertOk()
        ->assertSee('<meta http-equiv="refresh" content="0;url='.url('/store').'">', false);

    $this->get('/store')->assertRedirect();
});

it('turns a quick navigation answered by something other than a page into a full page load', function () {
    Route::middleware('web')->get('/spa-test-json', fn () => response()->json(['ok' => true]))->name('spa-test.json');

    $this->get('/spa-test-json', navigateRequest())
        ->assertOk()
        ->assertSee('<meta http-equiv="refresh" content="0;url='.url('/spa-test-json').'">', false);
});

it('leaves Livewire out of the pages that load fully, so their links and policy end with them', function () {
    // مكوّن عرضه اختبار سابق في العملية نفسها (لوحة الإدارة) يُبقي حقن Livewire الآلي مفعّلاً، وكل طلب في
    // الإنتاج يبدأ بلا حالة
    Livewire::flushState();

    expect($this->get('/login')->assertOk()->getContent())->not->toMatch('#/livewire(\.min)?\.js\?id=#')
        ->and($this->get('/')->getContent())->toMatch('#/livewire(\.min)?\.js\?id=#');
});

it('marks the pages of a signed-in member, so the browser back cache asks the server again', function () {
    expect($this->get('/')->getContent())->not->toContain('<meta name="signed-in"')
        ->and($this->actingAs(User::factory()->create())->get('/account')->getContent())->toContain('<meta name="signed-in" content="1">');
});

it('keeps the account area quick for a signed-in member, with its own pages marked', function () {
    $links = pageLinks($this->actingAs(User::factory()->create())->get('/account')->assertOk()->getContent());

    foreach (linkTo($links, route('account.requests.index')) as $link) {
        expect($link['navigate'])->toBeTrue($link['html']);
    }
});

it('checks a link against the routes without changing the parameters of the current route', function () {
    $route = Route::getRoutes()->match(Request::create(url('/governance/charter')));

    expect(SpaLink::allows(url('/governance/other-document')))->toBeTrue()
        ->and($route->parameter('slug'))->toBe('charter');
});

it('keeps the same decisions with the route cache, where unnamed routes get generated names', function () {
    $router = app('router');
    $router->setCompiledRoutes($router->getRoutes()->compile());

    expect(SpaLink::allows(url('/up')))->toBeFalse()
        ->and(SpaLink::allows(url('/governance')))->toBeTrue()
        ->and(SpaLink::allows(url('/governance/charter/download')))->toBeFalse()
        ->and(SpaLink::allows(url('/2019/05/some-old-post')))->toBeFalse();
});

it('gives an error page to a navigation as a page, so the browser does not ask for it again', function () {
    // Livewire يضعها مكان الصفحة برمز 200، فلا يحفظ رمز خطأ يحوّل الرجوع بعدها إلى تحميل كامل
    $this->get('/media/news/no-such-article', navigateRequest())
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee(__('errors.404.title'))
        ->assertDontSee('http-equiv="refresh"', false);

    $this->get('/media/news/no-such-article')->assertNotFound();
});

it('runs the controller of an error page once, and counts it once against its rate limit', function () {
    $runs = 0;
    Route::middleware(['web', 'throttle:5,1,spa-test'])->get('/spa-test-forbidden', function () use (&$runs) {
        $runs++;
        abort(403);
    })->name('spa-test.forbidden');

    // صفحة الخطأ هي الرد، لا صفحة تطلب الرابط نفسه مرة ثانية
    $this->get('/spa-test-forbidden', navigateRequest())
        ->assertOk()
        ->assertHeader('X-RateLimit-Remaining', '4')
        ->assertSee(__('errors.403.title'))
        ->assertDontSee('http-equiv="refresh"', false);

    expect($runs)->toBe(1);
});

it('loads the other host fully when a page answers a navigation by redirecting there', function () {
    Route::middleware('web')->get('/spa-test-away', fn () => redirect()->away('https://example.com/elsewhere?a=1&b=2'))->name('spa-test.away');

    expect(SpaLink::allows(url('/spa-test-away')))->toBeTrue();

    // Livewire كان سيتبع التحويل بطلب تمنعه سياسة connect-src فلا يحدث شيء
    $this->get('/spa-test-away', navigateRequest())
        ->assertOk()
        ->assertSee('<meta http-equiv="refresh" content="0;url=https://example.com/elsewhere?a=1&amp;b=2">', false);

    $this->get('/spa-test-away')->assertRedirect('https://example.com/elsewhere?a=1&b=2');
});

it('loads a full-load route fully when a page answers a navigation by redirecting to it', function () {
    Document::factory()->create(['slug' => 'charter']);
    Route::middleware('web')->get('/spa-test-to-file', fn () => redirect()->route('governance.download', 'charter'))->name('spa-test.to-file');

    expect(SpaLink::allows(url('/spa-test-to-file')))->toBeTrue();

    $this->get('/spa-test-to-file', navigateRequest())
        ->assertOk()
        ->assertSee('<meta http-equiv="refresh" content="0;url='.route('governance.download', 'charter').'">', false);
});

it('defers and tracks the Livewire script on public pages alone, so the admin panel served next does not inherit it', function () {
    expect($this->get('/')->getContent())->toMatch('#<script src="[^"]*/livewire(\.min)?\.js[^>]* defer="true" data-navigate-track="reload">#');

    // طلب تالٍ في العامل نفسه (Octane، والاختبارات): Livewire يمسح حالته بين الطلبات، وسماته تبقى
    Livewire::flushState();
    $admin = $this->actingAs(userWithRole(RoleName::TechnicalAdmin))->get('/admin')->assertOk()->getContent();

    expect(preg_match('#<script src="[^"]*/livewire(\.min)?\.js[^>]*>#', $admin, $script))->toBe(1)
        ->and($script[0])->not->toContain('defer')
        ->not->toContain('data-navigate-track');
});

it('lets quick navigation reach page controllers only, so no file controller runs twice', function () {
    // متحكم مسار يتنقّل إليه Livewire يرد بصفحة أو تحويل. والملف أو البيانات في مسار من FULL_PAGE_ROUTES، فلا
    // يصل إليه طلب التنقّل؛ وإلا شغّله ثم أعاد المتصفح طلبه تحميلاً كاملاً (FullPageLoadOnNavigate)
    $pages = [View::class, RenderedView::class, RedirectResponse::class];
    // و`Response` يحمل أي محتوى (PDF في طباعة النماذج، وXML في خريطة الموقع)، فمساراته المتنقّل إليها مراجَعة
    // بأسمائها: صفحة بترويسات (response()->view)
    $reviewedResponses = ['account.dashboard', 'account.my-reading', 'assembly.index', 'competitions.show', 'pages.preview', 'programs.read', 'programs.show', 'services.openings.show'];

    $files = collect(Route::getRoutes()->get('GET'))
        ->reject(fn (RoutingRoute $route): bool => $route->isFallback || Str::is(SpaLink::FULL_PAGE_ROUTES, (string) $route->getName()))
        ->map(function (RoutingRoute $route) use ($pages, $reviewedResponses): ?string {
            $uses = $route->getAction('uses');
            $action = match (true) {
                $uses instanceof Closure => new ReflectionFunction($uses),
                Str::contains($uses, '@') => new ReflectionMethod(...explode('@', $uses)),
                default => new ReflectionMethod($uses, '__invoke'),
            };

            // مسار بلا اسم لا يتنقّل إليه Livewire (SpaLink)، فيُحمَّل كاملاً. ومسارات الحزم بلا اسم (أصول Livewire،
            // وفحص /up) مقبولة، أما مسار التطبيق فيُسمّى، فيمر بهذا الفحص ولا يُنسى تحميلاً كاملاً بلا قصد
            if ($route->getName() === null || str_starts_with($route->getName(), 'generated::')) {
                return str_starts_with((string) $action->getFileName(), base_path('vendor')) ? null : 'بلا اسم: '.$route->uri();
            }

            $types = explode('|', ltrim((string) $action->getReturnType(), '?'));
            $allowed = in_array($route->getName(), $reviewedResponses, true) ? [...$pages, Response::class] : $pages;

            return array_diff($types, $allowed) === [] ? null : $route->getName().': '.(implode('|', array_filter($types)) ?: '—');
        })
        ->filter()
        ->values();

    expect($files)->toBeEmpty();
});

it('loads a server error page fully, so its real status reaches the server log', function () {
    Route::middleware('web')->get('/spa-test-broken', fn () => throw new RuntimeException('broken'))->name('spa-test.broken');

    // سجل nginx يرى 500 في طلب التحميل الكامل (docs/monitoring.md)
    $this->get('/spa-test-broken', navigateRequest())
        ->assertOk()
        ->assertSee('<meta http-equiv="refresh" content="0;url='.url('/spa-test-broken').'">', false);

    $this->get('/spa-test-broken')->assertInternalServerError();
});

it('loads a page over its rate limit fully, without counting the extra requests', function () {
    Route::middleware(['web', 'throttle:1,1,spa-test-limit'])->get('/spa-test-limited', fn () => 'ok')->name('spa-test.limited');

    $this->get('/spa-test-limited', navigateRequest())->assertOk();

    // الحد يرفض قبل أن يعد، فطلبا التنقّل والتحميل الكامل بعده لا يزيدان العدد، ولا تبقى صفحته في نسخة الرجوع
    $this->get('/spa-test-limited', navigateRequest())
        ->assertOk()
        ->assertSee('<meta http-equiv="refresh" content="0;url='.url('/spa-test-limited').'">', false);
    $this->get('/spa-test-limited')->assertTooManyRequests();

    expect(RateLimiter::attempts('spa-test-limit'.sha1('|127.0.0.1')))->toBe(1);
});

it('gives the not-found page of an implicit model binding to a navigation as a page, with the request nonce', function () {
    // الربط الضمني للنموذج يسبق المتحكم، وSecurityHeaders أول مجموعة web فصفحته برمز الطلب: تُعطى كما هي ككل صفحة
    // خطأ بقالب الموقع، بلا تحميل كامل. ويعتمد على هذا الترتيب (SecurityHeadersOrderTest)
    $this->actingAs(User::factory()->create())
        ->get('/account/requests/0f4c2a9e-0000-4000-8000-000000000000', navigateRequest())
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee(__('errors.404.title'))
        ->assertDontSee('http-equiv="refresh"', false);
});

it('loads an error page fully when its own template fails, since the fallback page carries no request nonce', function () {
    // قالب صفحة الخطأ يتعطل (القاعدة مثلاً): يرد لارافيل بصفحة Symfony الاحتياطية بأنماط مضمّنة، فلو وُضعت في الصفحة
    // لبقيت أنماطها في الرأس للصفحات التالية
    config(['app.debug' => false]);
    ViewFactory::composer('partials.header', fn () => throw new RuntimeException('header failed'));

    $this->get('/media/news/no-such-article', navigateRequest())
        ->assertOk()
        ->assertSee('<meta http-equiv="refresh" content="0;url='.url('/media/news/no-such-article').'">', false);
});

it('loads an address without a page fully when the admin panel navigates to it, without running the fallback', function () {
    // رابط في اللوحة لصفحة حُذفت يصل إلى المسار الاحتياطي: لا تُوضع صفحة الموقع العامة داخل اللوحة
    $this->get('/admin/no-such-page', navigateRequest())
        ->assertOk()
        ->assertSee('<meta http-equiv="refresh" content="0;url='.url('/admin/no-such-page').'">', false);

    $this->get('/admin/no-such-page')->assertNotFound();
});
