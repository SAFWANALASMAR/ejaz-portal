<?php

use App\Enums\AnnouncementKind;
use App\Enums\RoleName;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Models\Announcement;
use App\Models\Competition;
use App\Models\NewsArticle;
use App\Models\User;
use App\Support\Dates;
use App\Support\HomeShowcase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| «إعلانات» و«أخبار الجمعية» في الرئيسية (ملاحظة صفوان ٣٠، 3 نوفمبر 2026): الشريحة بصورتها أو غلاف الهوية، وشارتها، وعنوانها،
| ووصفها، وتاريخها، وزرها، وكبسولة الجوائز في صف البيانات. والخبر بصورته المصغرة وعنوانه وتاريخه وتصنيفه، و«كل الأخبار».
*/

/**
 * مقطع الإعلانات والأخبار من الرئيسية.
 */
function feedSection(): string
{
    $html = (string) test()->get('/')->assertOk()->getContent();

    return (string) str($html)->after('id="home-announcements"')->before('</section>');
}

it('shows an announcement slide with its image, kind badge, title, summary, date and details button', function () {
    Announcement::factory()->create([
        'title' => 'إعلان الاختبار',
        'summary' => 'وصف قصير للإعلان',
        'kind' => AnnouncementKind::Programs,
        'url' => '/programs',
        'image' => 'announcements/test.png',
        'starts_on' => '2026-11-01',
    ]);

    $html = feedSection();

    expect($html)->toContain('data-announcement-slide')
        ->and($html)->toContain('data-slide-media')
        ->and($html)->toContain(Storage::disk('public')->url('announcements/test.png'))
        ->and($html)->toContain(AnnouncementKind::Programs->getLabel())
        ->and($html)->toContain('إعلان الاختبار')
        ->and($html)->toContain('وصف قصير للإعلان')
        ->and($html)->toContain('datetime="2026-11-01"')
        ->and($html)->toContain(__('ui.feed.details'))
        ->and($html)->toContain('href="/programs"');
});

it('uses an identity cover without an image, and shows no summary line or button when they are empty', function () {
    Announcement::factory()->create(['title' => 'بلا صورة', 'image' => '', 'summary' => null, 'url' => null, 'kind' => AnnouncementKind::General]);

    $html = feedSection();

    expect($html)->toContain('feed-slide-media-cover')
        ->and($html)->toContain('images/brand/logo-mark-white.png')
        ->and($html)->not->toContain('data-slide-text')
        ->and($html)->not->toContain('feed-slide-cta');
});

it('shows the Brahin slide with its status, closing date, prize in the data row and a register button while open', function () {
    $competition = Competition::factory()->create([
        'title' => 'مسابقة البراهين',
        'student_mode' => true,
        'is_published' => true,
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDays(5),
        'page_content' => ['intro' => 'مقدمة المسابقة من نصوص صفحتها'],
        'prize_table' => [['male' => 1000, 'female' => 1000]],
    ]);

    $html = feedSection();
    $slide = (string) str($html)->after('data-announcement-slide')->before('</article>');

    expect($slide)->toContain(__('ui.feed.status.open'))
        ->and($slide)->toContain('مسابقة البراهين')
        ->and($slide)->toContain('مقدمة المسابقة من نصوص صفحتها')
        ->and($slide)->toContain(__('ui.feed.date.open'))
        ->and($slide)->toContain('datetime="'.Dates::local($competition->closes_at)->toIso8601String().'"')
        ->and($slide)->toContain('data-prize-total')
        ->and($slide)->toContain('data-count-to="2000">2,000')
        ->and($slide)->toContain(__('ui.feed.register'))
        ->and($slide)->toContain(route('competitions.show', $competition->slug));

    // الكبسولة داخل صف البيانات، قبل الزر
    expect(strpos($slide, 'feed-slide-meta'))->toBeLessThan(strpos($slide, 'data-prize-total'))
        ->and(strpos($slide, 'data-prize-total'))->toBeLessThan(strpos($slide, 'feed-slide-cta'));
});

it('labels an upcoming Brahin competition as coming soon with its opening date and a details button', function () {
    $competition = Competition::factory()->create([
        'student_mode' => true,
        'is_published' => true,
        'opens_at' => now()->addDays(3),
        'closes_at' => now()->addDays(10),
        'prize_table' => [['male' => 500, 'female' => 500]],
    ]);

    $slide = (string) str(feedSection())->after('data-announcement-slide')->before('</article>');

    expect($slide)->toContain(__('ui.feed.status.upcoming'))
        ->and($slide)->toContain(__('ui.feed.date.upcoming'))
        ->and($slide)->toContain('datetime="'.Dates::local($competition->opens_at)->toIso8601String().'"')
        ->and($slide)->toContain(__('ui.feed.details'))
        ->and($slide)->not->toContain(__('ui.feed.register'));
});

it('shows a closed Brahin competition as ended, without a register button', function () {
    Competition::factory()->create([
        'student_mode' => true,
        'is_published' => true,
        'opens_at' => now()->subDays(10),
        'closes_at' => now()->subDay(),
        'prize_table' => [['male' => 500, 'female' => 500]],
    ]);

    $slide = (string) str(feedSection())->after('data-announcement-slide')->before('</article>');

    expect($slide)->toContain(__('ui.feed.status.closed'))
        ->and($slide)->toContain(__('ui.feed.date.closed'))
        ->and($slide)->not->toContain(__('ui.feed.register'));
});

it('refreshes the cached home page when a Brahin competition opens or closes, and not otherwise', function () {
    Competition::factory()->create([
        'student_mode' => true,
        'is_published' => true,
        'opens_at' => now()->subDays(3),
        'closes_at' => now()->addMinute(),
        'prize_table' => [['male' => 500, 'female' => 500]],
    ]);

    expect(feedSection())->toContain(__('ui.feed.register'));

    // قبل الموعد لا مسح، فتبقى الرئيسية المخزنة
    expect(HomeShowcase::forgetHomeAtCompetitionBoundary(now()->subMinutes(2)))->toBeFalse();

    $this->travel(2)->minutes();
    expect(feedSection())->toContain(__('ui.feed.register'));

    expect(HomeShowcase::forgetHomeAtCompetitionBoundary(now()->subMinutes(2)))->toBeTrue()
        ->and(feedSection())->toContain(__('ui.feed.status.closed'))
        ->and(feedSection())->not->toContain(__('ui.feed.register'));
});

it('shows the competition date in Riyadh time with its hour, not the UTC day', function () {
    // 22:30 UTC يوم 9 = 01:30 بتوقيت الرياض يوم 10
    Competition::factory()->create([
        'student_mode' => true,
        'is_published' => true,
        'opens_at' => now()->subDay(),
        'closes_at' => '2099-11-09 22:30:00',
        'prize_table' => [['male' => 500, 'female' => 500]],
    ]);

    expect(feedSection())->toContain('datetime="2099-11-10T01:30:00+03:00"');
});

it('lists the news beside the announcements with a thumbnail, title, date, category and an all-news link', function () {
    Announcement::factory()->create();
    NewsArticle::factory()->create([
        'title' => 'خبر الاختبار',
        'category' => 'فعاليات',
        'image' => 'news/test.jpg',
        'published_on' => '2026-10-30',
        'is_published' => true,
    ]);

    $html = feedSection();
    $news = (string) str($html)->after('id="home-news"');

    expect((string) $this->get('/')->getContent())->toContain('lg:grid-cols-2')
        ->and($news)->toContain('data-news-card')
        ->and($news)->toContain('data-news-title')
        ->and($news)->toContain('خبر الاختبار')
        ->and($news)->toContain('data-news-category')
        ->and($news)->toContain('فعاليات')
        ->and($news)->toContain('datetime="2026-10-30"')
        ->and($news)->toContain(Storage::disk('public')->url('news/test.jpg'))
        ->and($news)->toContain('data-news-all')
        ->and($news)->toContain(__('ui.feed.all_news'))
        ->and($news)->toContain('href="'.route('media.news.index').'"');
});

it('saves the announcement summary from the admin panel, and rejects one that is too long', function () {
    Storage::fake('public');
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(CreateAnnouncement::class)
        ->fillForm([
            'title' => 'إعلان',
            'summary' => str_repeat('و', 301),
            'image' => UploadedFile::fake()->image('a.png'),
            'kind' => AnnouncementKind::General->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['summary' => 'max']);

    Livewire::test(CreateAnnouncement::class)
        ->fillForm([
            'title' => 'إعلان',
            'summary' => 'وصف قصير',
            'image' => UploadedFile::fake()->image('a.png'),
            'kind' => AnnouncementKind::General->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Announcement::query()->where('summary', 'وصف قصير')->exists())->toBeTrue();
});

it('does not let a visitor or a member without a panel role reach the announcement form', function () {
    $this->get('/admin/announcements/create')->assertRedirectContains('login');

    $this->actingAs(User::factory()->create())->get('/admin/announcements/create')->assertForbidden();
});

it('runs the home competition boundary task without failing when no competition is opening or closing', function () {
    // الرد false من الدالة كان يُسجَّل فشلاً للمهمة المجدولة كل دقيقة (فحص رابط العرض، 3 نوفمبر)
    $this->artisan('schedule:test', ['--name' => 'home-competition-boundary'])->assertSuccessful();
});
