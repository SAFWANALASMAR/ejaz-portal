<?php

use App\Actions\Library\SaveLibraryItem;
use App\Enums\LibrarySection;
use App\Enums\PartnerKind;
use App\Enums\RoleName;
use App\Filament\Resources\LibraryItems\LibraryItemResource;
use App\Filament\Resources\LibraryItems\Pages\EditLibraryItem;
use App\Filament\Resources\Partners\Pages\EditPartner;
use App\Filament\Resources\Partners\PartnerResource;
use App\Jobs\FetchHomeVideoThumbnail;
use App\Models\LibraryItem;
use App\Models\Partner;
use App\Models\Program;
use App\Models\ProgramType;
use App\Models\Setting;
use Database\Seeders\ProgramTypesSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * ملاحظات المدير التنفيذي على الرئيسية ومحور «الدورات والبرامج».
 * docs/requirements/12-executive-director-2026-10-10.md (القسمان 3 و4)
 */
beforeEach(function () {
    showLegacyHomeSections();
});

it('gives the competitions their own shortcut and names the programs shortcut courses and programs', function () {
    $this->get('/')->assertOk()
        ->assertSee(__('home.shortcuts.competitions.title'))
        ->assertSee('href="'.route('competitions.index').'"', false)
        ->assertSee('الدورات والبرامج');
});

it('shows up to three chosen published YouTube videos, playing only on request', function () {
    $chosen = LibraryItem::factory()->video('https://www.youtube.com/watch?v=aaaaaaaaaaa')->count(4)
        ->sequence(fn ($sequence) => ['title' => 'مرئي مختار '.$sequence->index, 'published_on' => now()->subDays($sequence->index)])
        ->create(['on_home' => true]);
    LibraryItem::factory()->video()->create(['title' => 'مرئي غير مختار']);
    LibraryItem::factory()->video()->unpublished()->create(['title' => 'مرئي مسودة', 'on_home' => true]);
    LibraryItem::factory()->create(['title' => 'كتاب مختار', 'section' => LibrarySection::Books, 'on_home' => true]);
    LibraryItem::factory()->video('https://media.example.org/talk.mp4')->create(['title' => 'مرئي مستضاف مختار', 'on_home' => true]);

    // مقطع المرئيات وحده: «مستجدات الجمعية» في الرئيسية تعرض أحدث المنشور من المكتبة كله (التصور النهائي)
    $html = (string) str((string) $this->get('/')->assertOk()->getContent())->after('id="home-videos"')->before('</section>');

    expect($html)->toContain(__('home.videos.title'))
        ->toContain('مرئي مختار 0')->toContain('مرئي مختار 2')
        ->not->toContain('مرئي مختار 3')
        ->not->toContain('مرئي غير مختار')
        ->not->toContain('مرئي مسودة')
        ->not->toContain('كتاب مختار')
        ->not->toContain('مرئي مستضاف مختار')
        ->toContain('data-video-embed="https://www.youtube-nocookie.com/embed/aaaaaaaaaaa?autoplay=1"')
        // وتشغيله من الرئيسية يُحسب في «قراءاتي» كصفحته (ملاحظات المدير التنفيذي، ليلة 4 نوفمبر 2026)
        ->toContain('data-progress="'.route('library.progress', [LibrarySection::Videos->value, $chosen->first()->slug]).'"')
        ->not->toContain('<iframe')
        ->toContain(route('library.section', LibrarySection::Videos->value));

    // الأحدث أولاً
    $this->get('/')->assertSeeInOrder(['مرئي مختار 0', 'مرئي مختار 1', 'مرئي مختار 2']);
});

// النسخة 63 من التصور: الحسابات الرسمية في مربع التذييل وحده، ولا مقطع «قنوات التواصل» في الرئيسية
it('shows the official accounts in the footer box, and no accounts section on the home page', function () {
    Setting::put('contact', ['social' => ['x' => 'https://x.com/example', 'youtube' => 'https://www.youtube.com/@example', 'instagram' => 'http://insecure.example']]);

    $html = (string) $this->get('/')->assertOk()->getContent();
    $footer = (string) str($html)->after('<footer');

    expect($html)->not->toContain('aria-labelledby="home-accounts"')
        ->and($footer)->toContain('href="https://x.com/example"')
        ->and($footer)->toContain('href="https://www.youtube.com/@example"')
        ->and($html)->not->toContain('insecure.example');
});

// «شركاؤنا» شريط واحد متحرك قبل التذييل (البند R17): الشركاء ثم الرعاة بترتيبهم
it('runs partners then sponsors in one moving strip before the footer', function () {
    Partner::factory()->create(['name' => 'شريك تجريبي']);
    Partner::factory()->create(['name' => 'راعٍ تجريبي', 'kind' => PartnerKind::Sponsor]);

    $html = (string) $this->get('/')->assertOk()->getContent();
    $strip = (string) str($html)->after('id="home-partners-track"')->before('data-marquee-copy');

    expect($html)->toContain(__('home.sections_final.headings.partners'))
        ->and(strpos($strip, 'شريك تجريبي'))->toBeLessThan(strpos($strip, 'راعٍ تجريبي'))
        ->and(strrpos($html, 'id="home-partners"'))->toBeLessThan(strpos($html, '<footer'));
});

it('keeps sponsors on the home page however many partners come before them', function () {
    Partner::factory()->count(13)->create(['sort' => 1]);
    Partner::factory()->create(['name' => 'راعٍ أخير', 'kind' => PartnerKind::Sponsor, 'sort' => 99]);

    $this->get('/')->assertOk()->assertSee('راعٍ أخير');
});

it('hides the videos and accounts sections when switched off', function () {
    LibraryItem::factory()->video()->create(['on_home' => true]);
    Setting::put('contact', ['social' => ['x' => 'https://x.com/example']]);
    showLegacyHomeSections(['show_videos' => false, 'show_accounts' => false]);

    $this->get('/')->assertOk()
        ->assertDontSee(__('home.videos.title'))
        ->assertDontSee(__('home.sections.channels.title'));
});

it('opens the courses and programs hub with training courses, scientific councils and competitions', function () {
    $this->seed(ProgramTypesSeeder::class);
    // شريط واحد بمراسٍ في الصفحة نفسها بالأنواع كلها (ملاحظات المدير التنفيذي، 4 نوفمبر 2026 — DirectorNotesNov4Test)
    $council = Program::factory()->for(ProgramType::query()->where('slug', 'scientific-councils')->sole(), 'type')->create();
    Program::factory()->for(ProgramType::query()->where('slug', 'training-courses')->sole(), 'type')->create();

    $this->get('/programs')->assertOk()
        ->assertSeeInOrder(['<h1', 'الدورات والبرامج'], false)
        ->assertSee('الدورات التدريبية')
        ->assertSee('المجالس العلمية')
        ->assertSee('href="#programs-training-courses"', false)
        ->assertSee('href="#programs-scientific-councils"', false)
        ->assertSee(route('competitions.index'), false);

    // اسم البطاقة يتبع اسم النوع في اللوحة
    ProgramType::query()->where('slug', 'training-courses')->update(['name' => 'دورات معدّلة']);
    $this->get('/programs')->assertOk()->assertSee('دورات معدّلة');

    // ونوع بلا برنامج منشور يبقى في الشريط، ومقطعه بحالته الفارغة (قرار صفوان، 11 نوفمبر 2026: «كل شيء ظاهر ولو فرغ»)
    $council->update(['is_published' => false]);

    $html = (string) $this->get('/programs')->assertOk()->assertSee('href="#programs-scientific-councils"', false)->getContent();

    expect((string) str($html)->after('data-programs-section="scientific-councils"')->before('</section>'))->toContain(__('programs.hub.empty'));
});

it('seeds every programme type once, keeping later edits', function () {
    $this->seed(ProgramTypesSeeder::class);
    ProgramType::query()->where('slug', 'training-courses')->update(['name' => 'اسم معدّل']);
    $this->seed(ProgramTypesSeeder::class);

    // نوعا المحور وأنواع برامج المكتبة الثلاثة (docs/decisions/2026-11-02-reading-program.md)
    expect(ProgramType::query()->count())->toBe(count(ProgramTypesSeeder::TYPES))
        ->and(ProgramType::query()->where('slug', 'training-courses')->value('name'))->toBe('اسم معدّل');
});

it('lets the content editor mark a partner as sponsor and a video for the home page, and nobody else', function () {
    Storage::fake('public');
    Storage::disk('public')->put('partners/logo.png', 'png');
    $partner = Partner::factory()->create(['logo' => 'partners/logo.png']);
    $video = LibraryItem::factory()->video()->create();

    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(EditPartner::class, ['record' => $partner->getRouteKey()])
        ->fillForm(['kind' => PartnerKind::Sponsor->value])->call('save')->assertHasNoFormErrors();
    Livewire::test(EditLibraryItem::class, ['record' => $video->getRouteKey()])
        ->fillForm(['on_home' => true])->call('save')->assertHasNoFormErrors();

    expect($partner->fresh()->kind)->toBe(PartnerKind::Sponsor)
        ->and($video->fresh()->on_home)->toBeTrue();

    // عضو المجلس يطّلع ولا يعدّل
    $this->actingAs(userWithRole(RoleName::BoardMember));
    $this->get(PartnerResource::getUrl('edit', ['record' => $partner]))->assertForbidden();
    $this->get(LibraryItemResource::getUrl('edit', ['record' => $video]))->assertForbidden();
});

it('clears the home page flag from anything that is not a YouTube video', function () {
    config(['security.csp.media_src' => ['https://media.example.org']]);
    $this->actingAs(userWithRole(RoleName::ContentEditor));
    $item = LibraryItem::factory()->video()->create(['on_home' => true]);

    app(SaveLibraryItem::class)->handle(auth()->user(), $item, ['video_url' => 'https://media.example.org/talk.mp4']);

    expect($item->fresh()->on_home)->toBeFalse();
});

// ملاحظة المدير التنفيذي (29 أكتوبر 2026 — docs/plans/visual-refresh.md): صورة المقطع الحقيقية قبل الضغط، من الخادم لا من جوجل
it('shows each chosen video with its stored image before it plays, and no request to Google from the visitor', function () {
    Storage::fake('public');
    Storage::disk('public')->put('library/yt-bbbbbbbbbbb-1.jpg', 'jpeg');
    LibraryItem::factory()->video('https://www.youtube.com/watch?v=bbbbbbbbbbb')->create(['title' => 'مرئي بصورته', 'on_home' => true, 'thumbnail' => 'library/yt-bbbbbbbbbbb-1.jpg']);

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('src="'.Storage::disk('public')->url('library/yt-bbbbbbbbbbb-1.jpg').'"')
        ->toContain('data-video-embed="https://www.youtube-nocookie.com/embed/bbbbbbbbbbb?autoplay=1"')
        ->not->toContain('ytimg.com')
        ->not->toContain('<iframe');
});

it('fetches the single home video image once on the server and shows it before it plays', function () {
    Storage::fake('public');
    Queue::fake();
    $png = imageBytes('png', 32, 18);
    Http::fake(['i.ytimg.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);

    Setting::put('home', [...Setting::group('home'), 'video_url' => 'https://www.youtube.com/watch?v=ccccccccccc']);
    FetchHomeVideoThumbnail::queueForCurrent();
    Queue::assertPushed(FetchHomeVideoThumbnail::class, fn (FetchHomeVideoThumbnail $job): bool => $job->youtube === 'ccccccccccc');

    // قبل الجلب: الخلفية الداكنة وحدها
    expect((string) $this->get('/')->getContent())->not->toContain('data-video-thumbnail');

    (new FetchHomeVideoThumbnail('ccccccccccc'))->handle();
    $path = FetchHomeVideoThumbnail::path('ccccccccccc');

    Storage::disk('public')->assertExists($path);
    expect(Storage::disk('public')->get($path))->toStartWith("\xFF\xD8");
    Http::assertSentCount(1);

    $html = (string) $this->get('/')->assertOk()->getContent();
    expect($html)->toContain('src="'.Storage::disk('public')->url($path).'"')
        ->not->toContain('ytimg.com')
        ->not->toContain('<iframe');

    // ولا يُطلب ثانية ما دامت الصورة موجودة، وتنظيف القرص العام يتركها
    FetchHomeVideoThumbnail::queueForCurrent();
    Queue::assertPushed(FetchHomeVideoThumbnail::class, 1);
    $this->artisan('ejaz:prune-public-files')->assertSuccessful();
    Storage::disk('public')->assertExists($path);
});
