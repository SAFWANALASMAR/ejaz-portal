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
        ->not->toContain('<iframe')
        ->toContain(route('library.section', LibrarySection::Videos->value));

    // الأحدث أولاً
    $this->get('/')->assertSeeInOrder(['مرئي مختار 0', 'مرئي مختار 1', 'مرئي مختار 2']);
});

it('shows the official accounts from the site settings, and no section without them', function () {
    $this->get('/')->assertDontSee(__('home.sections.channels.title'));

    Setting::put('contact', ['social' => ['x' => 'https://x.com/example', 'youtube' => 'https://www.youtube.com/@example', 'instagram' => 'http://insecure.example']]);

    $html = (string) $this->get('/')->assertOk()->getContent();
    // المقطع وحده، فالتذييل يعرض الحسابات أيضاً
    $section = (string) str($html)->after('aria-labelledby="home-accounts"')->before('</section>');

    expect($html)->toContain(__('home.sections.channels.title'))
        ->and($section)->toContain(__('settings.social.x'))
        ->toContain(__('settings.social.youtube'))
        ->toContain('href="https://x.com/example"')
        ->toContain('rel="noopener noreferrer"')
        ->not->toContain('insecure.example');
});

it('groups partners and sponsors, and keeps one group without sponsors', function () {
    Partner::factory()->create(['name' => 'شريك تجريبي']);

    $this->get('/')->assertOk()
        ->assertSee(__('home.partners.title'))
        ->assertDontSee('>'.__('home.partners.sponsors').'</h3>', false);

    Partner::factory()->create(['name' => 'راعٍ تجريبي', 'kind' => PartnerKind::Sponsor]);

    $this->get('/')->assertOk()
        ->assertSeeInOrder(['>'.__('home.partners.partners').'</h3>', 'شريك تجريبي', '>'.__('home.partners.sponsors').'</h3>', 'راعٍ تجريبي'], false);
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

    $this->get('/programs')->assertOk()
        ->assertSeeInOrder(['<h1', 'الدورات والبرامج'], false)
        ->assertSee('الدورات التدريبية')
        ->assertSee('المجالس العلمية')
        ->assertSee(route('programs.index', ['type' => 'training-courses']), false)
        ->assertSee(route('programs.index', ['type' => 'scientific-councils']), false)
        ->assertSee(route('competitions.index'), false);

    // اسم البطاقة يتبع اسم النوع في اللوحة
    ProgramType::query()->where('slug', 'training-courses')->update(['name' => 'دورات معدّلة']);
    $this->get('/programs')->assertOk()->assertSee('دورات معدّلة');

    ProgramType::query()->where('slug', 'scientific-councils')->delete();

    $this->get('/programs')->assertOk()->assertDontSee(route('programs.index', ['type' => 'scientific-councils']), false);
});

it('seeds the two programme types once, keeping later edits', function () {
    $this->seed(ProgramTypesSeeder::class);
    ProgramType::query()->where('slug', 'training-courses')->update(['name' => 'اسم معدّل']);
    $this->seed(ProgramTypesSeeder::class);

    expect(ProgramType::query()->count())->toBe(2)
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
