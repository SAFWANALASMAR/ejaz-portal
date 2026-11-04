<?php

use App\Enums\LibrarySection;
use App\Models\Announcement;
use App\Models\Competition;
use App\Models\LibraryItem;
use App\Models\NewsArticle;
use App\Models\Opening;
use App\Models\Partner;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Blade;

/*
| فحص الوصول 0.15 (docs/reviews/2026-11-02-v0.15-a11y.md): ما يُختبر في HTML من إصلاحاته. والتركيز والتباين والحركة في المتصفح
| (tests/browser/v015-a11y.spec.mjs) وفي tests/Unit/FocusAndDotContrastTest.php.
*/

it('hides the counting digits from screen readers and gives them the final number once', function () {
    $badge = Blade::render('<x-stat-badge :value="12500" label="مستفيد" suffix="+" />');

    // العدّ يبدّل النص الظاهر، فلا يُقرأ رقماً عابراً، والنهائي في نسخة ثابتة لقارئ الشاشة
    expect($badge)->toMatch('/<span class="stat-number[^"]*" aria-hidden="true"\s+data-count-to="12500"/')
        ->toContain('<span class="sr-only">12,500+</span>');
});

it('keeps the Brahin prize count-up out of the announcements live region', function () {
    Competition::factory()->create([
        'student_mode' => true,
        'is_published' => true,
        'prize_table' => [['male' => 2000, 'female' => 2000]],
    ]);

    $carousel = (string) str($this->get('/')->assertOk()->getContent())->after('data-prize-total')->before('</a>');

    expect($carousel)->toContain('aria-hidden="true" data-count-to="4000">4,000</span>')
        ->toContain('<span class="sr-only">4,000</span>');
});

it('names the section of every «more» and «view all» link on the home page', function () {
    NewsArticle::factory()->create(['is_published' => true]);
    Announcement::factory()->create(['is_published' => true]);
    Partner::factory()->create(['is_published' => true]);
    LibraryItem::factory()->video()->create(['thumbnail' => 'library/thumbs/a.jpg']);
    LibraryItem::factory()->create(['section' => LibrarySection::Books, 'thumbnail' => 'library/thumbs/b.jpg']);

    $html = $this->get('/')->assertOk()->getContent();
    preg_match_all('/<a [^>]*class="more-link[^"]*"[^>]*>(.*?)<\/a>/su', $html, $links);

    // الروابط بنص عام وحدها («المزيد» و«عرض الكل»)، لا ما نصه يصف وجهته («كل المبادرات»): الإعلانات والأخبار والمقاطع والكتب والشركاء
    $generic = array_values(array_filter($links[1], fn (string $text): bool => str_starts_with($text, __('ui.news.more')) || str_starts_with($text, __('site.view_all'))));

    expect($generic)->toHaveCount(5);

    foreach ($generic as $text) {
        expect($text)->toMatch('/<span class="sr-only">: [^<]+<\/span>/');
    }

    // ولا يتكرر اسم رابطين
    expect(array_unique($generic))->toHaveCount(5);
});

it('names the section of the «view all» links on the account home', function () {
    $user = User::factory()->create();
    // «عرض الكل» يظهر متى كان في القسم شيء (ملاحظة المقيّم على #167)، فللمستخدم موعد قادم وطلب
    ServiceRequest::factory()->for($user)->for(Opening::factory()->create())->create();
    ProgramRegistration::factory()->for($user)->for(Program::factory()->create(['starts_at' => now()->addDays(2)]))->create();

    $html = $this->actingAs($user)->get('/account')->assertOk()->getContent();

    expect($html)
        ->toContain(__('account.summary.view_all').'<span class="sr-only">: '.__('account.summary.upcoming').'</span>')
        ->toContain(__('account.summary.view_all').'<span class="sr-only">: '.__('account.summary.latest_requests').'</span>');
});

it('puts the marquee pause button before the strip in focus order', function () {
    $html = Blade::render('<x-marquee id="m1" label="شريط"><a href="#">عنصر</a></x-marquee>');

    // يصل إليه مستخدم لوحة المفاتيح قبل بطاقات الشريط كلها، كما يظهر في زاويته العليا
    expect(strpos($html, 'data-ticker-toggle="m1"'))->toBeLessThan(strpos($html, 'id="m1"'));
});

it('names each marquee pause button after its strip, since the button sits outside the strip region', function () {
    $html = Blade::render('<x-marquee id="m1" label="أخبار الجمعية"><a href="#">عنصر</a></x-marquee>');

    expect($html)->toContain('aria-label="'.__('ui.marquee.pause_named', ['name' => 'أخبار الجمعية']).'"')
        ->toContain('data-label-play="'.__('ui.marquee.play_named', ['name' => 'أخبار الجمعية']).'"');
});

it('gives the reader zoom reset button a name that contains its visible percentage', function () {
    $html = view('library.reader', ['url' => '/library/books/a/file', 'title' => 'كتاب'])->render();

    // الاسم «إعادة الحجم: 100%» يحوي النص الظاهر (2.5.3)، ولا aria-label يحجبه
    $button = (string) str($html)->after('data-pdf-zoom-reset')->before('</button>');

    expect($button)->not->toContain('aria-label')
        ->toContain('<span class="sr-only">'.__('library.reader.zoom_reset').': </span><span data-pdf-zoom dir="ltr">100%</span>');
});

it('names the admin sidebar brand link even when the sidebar shows the mark alone', function () {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));

    $html = view('filament.admin.sidebar-brand')->render();

    // الشريط المطوي والجوال يخفيان المخطوطة، والرمز بلا نص بديل، فالاسم على الرابط نفسه
    expect($html)->toContain('class="ej-sidebar-brand" aria-label="'.__('admin.theme.logo_alt').'"');
});
