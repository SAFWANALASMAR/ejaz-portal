<?php

use App\Enums\RoleName;
use App\Models\DailyVisit;
use App\Models\FormEntry;
use App\Support\PortalStats;
use App\Support\Visits;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * رؤوس متصفح حقيقي، ووكيله قابل للتغيير ليصير زائراً آخر.
 *
 * @param  array<string, string>  $headers
 * @return array<string, string>
 */
function asBrowser(array $headers = [], string $agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36'): array
{
    return ['User-Agent' => $agent, ...$headers];
}

/**
 * أرقام يوم (اليوم بتوقيت الرياض افتراضياً) كما ينقلها ejaz:refresh-stats إلى القاعدة.
 *
 * @return array{views: int, visitors: int}
 */
function visitsOf(?string $date = null): array
{
    test()->artisan('ejaz:refresh-stats')->assertSuccessful();
    $row = DailyVisit::query()->where('date', $date ?? now(config('app.display_timezone'))->toDateString())->first();

    return ['views' => $row->views ?? 0, 'visitors' => $row->visitors ?? 0];
}

it('counts the page views of the day and its unique visitors', function () {
    $this->get('/', asBrowser())->assertOk();
    $this->get('/', asBrowser())->assertOk();

    expect(visitsOf())->toBe(['views' => 2, 'visitors' => 1]);

    // متصفح آخر من العنوان نفسه، والمتصفح الأول من عنوان آخر: زائران جديدان
    $this->get('/', asBrowser(agent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Safari/604.1'))->assertOk();
    $this->call('GET', '/', server: ['REMOTE_ADDR' => '10.0.0.9', 'HTTP_USER_AGENT' => asBrowser()['User-Agent']])->assertOk();

    expect(visitsOf())->toBe(['views' => 4, 'visitors' => 3]);
});

it('counts a page reached by quick navigation, and a navigation that becomes a full page load only by its load', function () {
    $navigate = asBrowser(['X-Livewire-Navigate' => '1', 'Sec-Fetch-Dest' => 'empty']);

    $this->get('/', $navigate)->assertOk();
    expect(visitsOf())->toBe(['views' => 1, 'visitors' => 1]);

    // صفحة تُحمَّل كاملاً (الدخول)، وتحويل الزائر من الحساب إلى الدخول: صفحة تحميل كامل لا تُعدّ، ثم يُعدّ تحميلها
    $this->get('/login', $navigate)->assertOk()->assertViewIs('partials.full-page-load');
    $this->get('/account', $navigate)->assertOk()->assertViewIs('partials.full-page-load');
    expect(visitsOf())->toBe(['views' => 1, 'visitors' => 1]);

    $this->get('/login', asBrowser(['Sec-Fetch-Dest' => 'document']))->assertOk();
    expect(visitsOf())->toBe(['views' => 2, 'visitors' => 1]);
});

it('counts only successful pages that a browser asks for', function () {
    Route::middleware('web')->group(function (): void {
        Route::post('/visits-test/form', fn () => response('<p>تم</p>'));
        Route::get('/visits-test/data', fn () => ['ok' => true]);
        Route::get('/visits-test/file', fn () => response()->streamDownload(fn () => print ('%PDF-1.4'), 'file.pdf', ['Content-Type' => 'application/pdf']));
        Route::get('/visits-test/page-file', fn () => response('<p>ملف</p>', headers: ['Content-Disposition' => 'attachment; filename="page.html"']));
        Route::get('/visits-test/missing', fn () => abort(404));
        Route::get('/visits-test/broken', fn () => throw new RuntimeException('broken'));
        Route::get('/visits-test/moved', fn () => redirect('/'));
    });

    $this->post('/visits-test/form', [], asBrowser())->assertOk();
    $this->get('/visits-test/data', asBrowser())->assertOk();
    $this->get('/visits-test/file', asBrowser())->assertOk();
    $this->get('/visits-test/page-file', asBrowser())->assertOk();
    $this->get('/visits-test/missing', asBrowser())->assertNotFound();
    $this->get('/visits-test/broken', asBrowser())->assertServerError();
    $this->get('/visits-test/moved', asBrowser())->assertRedirect('/');
    $this->call('HEAD', '/', server: ['HTTP_USER_AGENT' => asBrowser()['User-Agent']])->assertOk();

    // الزواحف الظاهرة باسمها، وما ليس متصفحاً، والطلب بلا وكيل
    $this->get('/', asBrowser(agent: 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'))->assertOk();
    $this->get('/', asBrowser(agent: 'Mozilla/5.0 (compatible; AhrefsCrawler/1.0)'))->assertOk();
    $this->get('/', asBrowser(agent: 'Mozilla/5.0 (compatible; Baiduspider/2.0)'))->assertOk();
    $this->get('/', asBrowser(agent: 'facebookexternalhit/1.1'))->assertOk();
    $this->get('/', asBrowser(agent: 'curl/8.4.0'))->assertOk();
    $this->get('/', asBrowser(agent: ''))->assertOk();

    // الجلب المسبق، والجلب من شيفرة صفحة بلا تنقّل، والصفحة داخل إطار
    $this->get('/', asBrowser(['Sec-Purpose' => 'prefetch']))->assertOk();
    $this->get('/', asBrowser(['Sec-Purpose' => 'prefetch;prerender']))->assertOk();
    $this->get('/', asBrowser(['Purpose' => 'prefetch']))->assertOk();
    $this->get('/', asBrowser(['Sec-Fetch-Dest' => 'empty']))->assertOk();
    $this->get('/', asBrowser(['Sec-Fetch-Dest' => 'iframe']))->assertOk();

    expect(visitsOf())->toBe(['views' => 0, 'visitors' => 0]);

    $this->get('/', asBrowser(['Sec-Fetch-Dest' => 'document']))->assertOk();

    expect(visitsOf())->toBe(['views' => 1, 'visitors' => 1]);
});

it('does not count the admin panel, its print view of a filled form, or Horizon', function () {
    $this->actingAs(userWithRole(RoleName::SuperAdmin));

    $this->get('/admin', asBrowser())->assertOk();
    $this->get('/horizon', asBrowser())->assertOk();
    $print = $this->get(route('documents.entries.print', FormEntry::factory()->create()), asBrowser(['Sec-Fetch-Dest' => 'document']))->assertOk();

    // صفحة HTML كصفحات الموقع، فلا يستثنيها إلا اسم مسارها
    expect($print->headers->get('Content-Type'))->toStartWith('text/html')
        ->and(visitsOf())->toBe(['views' => 0, 'visitors' => 0]);

    // صفحات الموقع تُعدّ لصاحب الحساب كما للزائر
    $this->get('/', asBrowser())->assertOk();

    expect(visitsOf())->toBe(['views' => 1, 'visitors' => 1]);
});

it('starts a new day at midnight in Riyadh, and keeps the final count of the day before', function () {
    $this->travelTo(Carbon::parse('2026-10-08 23:50', 'Asia/Riyadh'));
    $this->get('/', asBrowser())->assertOk();
    $this->artisan('ejaz:refresh-stats');
    $this->get('/', asBrowser())->assertOk();

    // آخر زيارات اليوم بعد آخر نقل تُنقل في أول تشغيل بعد منتصف الليل، والزائر نفسه يُعدّ في يومه الجديد
    $this->travelTo(Carbon::parse('2026-10-09 00:10', 'Asia/Riyadh'));
    $this->get('/', asBrowser())->assertOk();
    $this->artisan('ejaz:refresh-stats');

    expect(DailyVisit::query()->orderBy('date')->get()->map(fn (DailyVisit $day): array => [$day->date->toDateString(), $day->views, $day->visitors])->all())->toBe([
        ['2026-10-08', 2, 1],
        ['2026-10-09', 1, 1],
    ]);
});

it('keeps neither the address nor the browser of a visitor', function () {
    $this->call('GET', '/', server: ['REMOTE_ADDR' => '10.20.30.40', 'HTTP_USER_AGENT' => 'Mozilla/5.0 UniqueBrowser/9.9'])->assertOk();
    $this->artisan('ejaz:refresh-stats');

    $storage = (fn (): array => $this->storage)->call(Cache::store()->getStore());
    $stored = serialize($storage);

    // ولا بصمة بلا مفتاح يُستخرج منها العنوان بالتجربة، وكل مفتاح للعدّاد له مدة
    expect($stored)->not->toContain('10.20.30.40')
        ->not->toContain('UniqueBrowser')
        ->not->toContain(hash('sha256', '10.20.30.40|Mozilla/5.0 UniqueBrowser/9.9|'.Visits::today()))
        ->and(collect($storage)->filter(fn (array $item, string $key): bool => str_starts_with($key, 'visits:'))->pluck('expiresAt')->all())->each->toBeGreaterThan(0)
        ->and(DailyVisit::query()->sole()->only(['views', 'visitors']))->toBe(['views' => 1, 'visitors' => 1])
        ->and(Schema::getColumnListing('daily_visits'))->toEqualCanonicalizing(['id', 'date', 'views', 'visitors', 'created_at', 'updated_at']);
});

it('keeps the fingerprint of a visitor to its own day, with the date in its key and in its secret hash', function () {
    $server = ['REMOTE_ADDR' => '10.20.30.40', 'HTTP_USER_AGENT' => 'Mozilla/5.0 UniqueBrowser/9.9'];
    $fingerprint = fn (string $date): string => 'visits:'.$date.':visitor:'.hash_hmac('sha256', '10.20.30.40|Mozilla/5.0 UniqueBrowser/9.9|'.$date, (string) config('app.key'));

    $this->travelTo(Carbon::parse('2026-10-08 23:50', 'Asia/Riyadh'));
    $this->call('GET', '/', server: $server)->assertOk();

    expect(Cache::has($fingerprint('2026-10-08')))->toBeTrue();

    // بعد نهاية يومها بهامشه لا تبقى، وعدّادا يومها باقيان حتى ينقلهما أول تشغيل بعد منتصف الليل.
    // وبصمة الزائر نفسه في اليوم التالي غير بصمته في يومه، فلا يُربط يوم بيوم
    $this->travelTo(Carbon::parse('2026-10-09 00:10', 'Asia/Riyadh'));
    $this->call('GET', '/', server: $server)->assertOk();

    expect(Cache::has($fingerprint('2026-10-08')))->toBeFalse()
        ->and(Cache::has($fingerprint('2026-10-09')))->toBeTrue()
        ->and(visitsOf('2026-10-08'))->toBe(['views' => 1, 'visitors' => 1])
        ->and(visitsOf('2026-10-09'))->toBe(['views' => 1, 'visitors' => 1]);
});

it('moves the visits before computing the numbers, and does both every ten minutes on one server', function () {
    $this->get('/', asBrowser())->assertOk();
    $this->get('/', asBrowser())->assertOk();

    $this->artisan('ejaz:refresh-stats')->assertSuccessful();

    expect(PortalStats::cached())->toMatchArray(['views_today' => 2, 'visitors_today' => 1]);

    $events = collect(app(Schedule::class)->events())->filter(fn (Event $event): bool => str_contains((string) $event->command, 'ejaz:refresh-stats'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('*/10 * * * *')
        ->and($events->first()->onOneServer)->toBeTrue();
});

it('moves the counters to the database by the command, updating a day and never lowering it', function () {
    foreach (range(1, 3) as $visit) {
        $this->get('/', asBrowser())->assertOk();
    }

    // قبل الأمر لا شيء في القاعدة: العدّ في الذاكرة المؤقتة، والنقل في الخلفية
    expect(DailyVisit::query()->count())->toBe(0);

    expect(visitsOf())->toBe(['views' => 3, 'visitors' => 1])
        ->and(visitsOf())->toBe(['views' => 3, 'visitors' => 1])
        ->and(DailyVisit::query()->count())->toBe(1);

    // ذاكرة أُخليت فبدأ عدّها من الصفر: المحفوظ لا ينقص، ثم يتبع العدّ إذا تجاوزه
    Cache::flush();
    $this->get('/', asBrowser())->assertOk();
    expect(visitsOf())->toBe(['views' => 3, 'visitors' => 1]);

    foreach (range(1, 3) as $visit) {
        $this->get('/', asBrowser(agent: 'Mozilla/5.0 (Macintosh) Safari/605.1.15 visit-'.$visit))->assertOk();
    }
    expect(visitsOf())->toBe(['views' => 4, 'visitors' => 4])
        ->and(DailyVisit::query()->count())->toBe(1);
});

it('keeps the page when the counter fails', function () {
    Exceptions::fake();

    // ذاكرة تعمل لكل شيء إلا عدّاد الزيارات
    Cache::extend('failing-visits', fn (): Repository => Cache::repository(new class extends ArrayStore
    {
        public function increment($key, $value = 1)
        {
            throw_if(str_starts_with($key, 'visits:'), new RuntimeException('cache down'));

            return parent::increment($key, $value);
        }
    }));
    config(['cache.stores.failing-visits' => ['driver' => 'failing-visits']]);
    Cache::setDefaultDriver('failing-visits');

    $this->get('/', asBrowser())->assertOk();

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'cache down');
});
