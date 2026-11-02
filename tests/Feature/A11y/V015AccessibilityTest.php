<?php

use App\Models\Announcement;
use App\Models\Competition;
use App\Models\NewsArticle;
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

it('names the section of the announcements and news «more» links on the home page', function () {
    NewsArticle::factory()->create(['is_published' => true]);
    Announcement::factory()->create(['is_published' => true]);

    $html = $this->get('/')->assertOk()->getContent();

    // «المزيد» مرتان بالنص نفسه، فلكل منهما اسم قسمه مخفياً بصرياً (روابط الشرطين والشركاء في fix/marquee-pause)
    expect($html)
        ->toContain(__('ui.announcements.more').'<span class="sr-only">: ')
        ->toContain(__('ui.news.more').'<span class="sr-only">: ')
        ->and(substr_count($html, 'class="more-link">'.__('ui.news.more').'<span class="sr-only">: '))->toBe(2);
});

it('names the section of the «view all» links on the account home', function () {
    $user = User::factory()->create();

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
