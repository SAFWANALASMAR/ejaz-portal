<?php

use App\Enums\DocumentCategory;
use App\Enums\LibrarySection;
use App\Enums\RoleName;
use App\Filament\Pages\HomeContentPage;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\AssistantGuides\Pages\CreateAssistantGuide;
use App\Filament\Resources\Initiatives\Pages\CreateInitiative;
use App\Models\Document;
use App\Models\LibraryItem;
use App\Models\NewsArticle;
use App\Models\Setting;
use App\Support\ContentCache;
use App\Support\HomeContent;
use App\Support\NeutralImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| فحص الموقع الحي كاملاً (11 نوفمبر 2026، بأمر صفوان — docs/decisions/2026-11-11-fix-site-audit-nov11.md، ومنخفضات مراجعته في
| docs/decisions/2026-11-11-fix-site-audit-lows.md): صورة القسم لصفحاته
| الفرعية، وخانة المتجر، ووصف عنصر المكتبة وعنوانه، وزر الأهداف. والبندان ٣ و٤ لم يُنفَّذا (ينقضان توجيه المدير، 3 نوفمبر).
*/

function auditHeroImage(string $html): string
{
    preg_match('/<span class="page-hero-frame">\s*<img src="([^"]+)"/', $html, $match);

    return $match[1] ?? '';
}

function uploadPageHero(string $key, string $path): string
{
    Storage::disk('public')->put($path, 'x');
    $stored = Setting::group(HomeContent::KEY);
    $stored['page_heroes'][$key] = $path;
    Setting::put(HomeContent::KEY, $stored);
    ContentCache::flush();

    return Storage::disk('public')->url($path);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function auditLibraryItem(array $attributes): LibraryItem
{
    $item = LibraryItem::factory()->create($attributes);

    if (filled($item->file)) {
        Storage::disk(LibraryItem::disk())->put((string) $item->file, pdfContents());
    }

    return $item;
}

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

it('gives the section image to its inner pages, after the page own upload and the item image, before the neutral pattern', function () {
    Document::factory()->create(['slug' => 'policy', 'category' => DocumentCategory::Policies]);

    // قبل الرفع: نقش القسم، والصفحة مخزّنة
    expect(auditHeroImage($this->get('/governance/policy')->assertOk()->getContent()))->toBe(asset('images/placeholders/pages/governance.svg'));

    // الرفع بالحفظ الفعلي من «محتوى الرئيسية»، بلا تفريغ يدوي للمخزّن: الحفظ وحده يحدّث الصفحة المخزّنة
    $this->actingAs(userWithRole(RoleName::ContentEditor));
    Livewire::test(HomeContentPage::class)
        ->fillForm(['page_heroes.governance' => UploadedFile::fake()->image('governance.jpg', 1600, 600)])
        ->call('save')
        ->assertHasNoFormErrors();
    $governance = Storage::disk('public')->url((string) (Setting::group(HomeContent::KEY)['page_heroes']['governance'] ?? ''));

    // وثيقة الحوكمة بلا صورة لها: صورة قسمها
    expect(auditHeroImage($this->get('/governance/policy')->assertOk()->getContent()))->toBe($governance)
        ->and(HomeContent::sectionHeroUrl('governance.show'))->toBe($governance)
        ->and(HomeContent::pageHeroUrl('governance.show'))->toBeNull();

    // الخبر بصورته لا تغلبه صورة قسمه
    NewsArticle::factory()->create(['slug' => 'with-cover', 'image' => 'news/cover.jpg']);
    Storage::disk('public')->put('news/cover.jpg', 'x');
    $news = uploadPageHero('news', 'settings/news-hero.jpg');
    $article = auditHeroImage($this->get('/media/news/with-cover')->assertOk()->getContent());
    expect($article)->toContain('news/cover.jpg')->not->toBe($news);

    // وصفحة القسم نفسه بصورتها، وصفحة فرعية بلا صورة قسم مرفوعة على نقشها
    expect(auditHeroImage($this->get('/governance')->assertOk()->getContent()))->toBe($governance)
        ->and(HomeContent::sectionHeroUrl('initiatives.show'))->toBeNull()
        ->and(NeutralImage::forPage('initiatives.show'))->toBe(asset('images/placeholders/pages/initiatives.svg'));
});

it('picks the section by the longest route prefix, as the neutral pattern does', function () {
    expect(HomeContent::pageKey('media.news.show'))->toBe('news')
        ->and(HomeContent::pageKey('media.social'))->toBe('media')
        ->and(HomeContent::pageKey('governance.show'))->toBe('governance')
        ->and(HomeContent::pageKey('services.openings.show'))->toBe('services')
        ->and(HomeContent::pageKey('contact'))->toBe('contact')
        ->and(HomeContent::pageKey('privacy'))->toBeNull()
        ->and(HomeContent::pageKey(null))->toBeNull();

    $media = uploadPageHero('media', 'settings/media-hero.jpg');
    expect(HomeContent::sectionHeroUrl('media.news.show'))->toBeNull()
        ->and(HomeContent::sectionHeroUrl('media.social'))->toBe($media);
});

it('adds a header image slot for the store page, with its Arabic label and neutral pattern', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(HomeContentPage::class)->assertFormFieldExists('page_heroes.store')
        ->assertSee(__('settings.home_content.page_hero_store'));

    expect(HomeContent::PAGE_HEROES['store'])->toBe('store.index')
        ->and(public_path('images/placeholders/pages/store.svg'))->toBeFile()
        ->and(auditHeroImage($this->get('/store')->assertOk()->getContent()))->toBe(asset('images/placeholders/pages/store.svg'));

    $store = uploadPageHero('store', 'settings/store-hero.jpg');
    expect(auditHeroImage($this->get('/store')->assertOk()->getContent()))->toBe($store);
});

it('describes a library item without a description by its title, section and author, and names the page with its section', function () {
    auditLibraryItem(['slug' => 'naseya-audio', 'title' => 'الناصية', 'section' => LibrarySection::Audio, 'file' => LibraryItem::FILE_DIRECTORY.'/a.mp3', 'description' => null, 'author' => 'محاضر']);
    LibraryItem::factory()->video('https://www.youtube.com/watch?v=abcdefghijk')->create(['slug' => 'naseya-video', 'title' => 'الناصية', 'description' => null, 'author' => null]);

    $audio = (string) $this->get('/library/audio/naseya-audio')->assertOk()->getContent();
    $video = (string) $this->get('/library/videos/naseya-video')->assertOk()->getContent();

    $description = fn (string $html): string => html_entity_decode((string) str($html)->match('/<meta name="description" content="([^"]*)"/'));
    $title = fn (string $html): string => html_entity_decode((string) str($html)->match('/<title>([^<]*)<\/title>/'));
    $ogTitle = fn (string $html): string => html_entity_decode((string) str($html)->match('/<meta property="og:title" content="([^"]*)"/'));

    expect($description($audio))->toBe(__('library.item.meta_description_author', ['title' => 'الناصية', 'author' => 'محاضر', 'section' => LibrarySection::Audio->getLabel(), 'library' => __('library.title')]))
        ->and($description($video))->toBe(__('library.item.meta_description', ['title' => 'الناصية', 'section' => LibrarySection::Videos->getLabel(), 'library' => __('library.title')]))
        ->and($title($audio))->toContain('الناصية — '.LibrarySection::Audio->getLabel())
        ->and($title($video))->toContain('الناصية — '.LibrarySection::Videos->getLabel())
        ->and($title($audio))->not->toBe($title($video))
        ->and($ogTitle($audio))->toBe('الناصية — '.LibrarySection::Audio->getLabel());

    // والوصف المكتوب يبقى كما هو
    auditLibraryItem(['slug' => 'with-text', 'title' => 'كتاب', 'section' => LibrarySection::Books, 'description' => 'وصف مكتوب للكتاب']);
    expect($description((string) $this->get('/library/books/with-text')->assertOk()->getContent()))->toBe('وصف مكتوب للكتاب');
});

it('keeps the fallback description within 160 characters, cutting the title and keeping the section', function () {
    auditLibraryItem(['slug' => 'long-title', 'title' => str_repeat('عنوان طويل جداً لكتاب ', 20), 'section' => LibrarySection::Books, 'description' => null, 'author' => 'مؤلف الكتاب']);

    $html = (string) $this->get('/library/books/long-title')->assertOk()->getContent();
    $description = html_entity_decode((string) str($html)->match('/<meta name="description" content="([^"]*)"/'));

    expect(mb_strlen($description))->toBeLessThanOrEqual(160)
        ->and($description)->toContain('…')
        ->and($description)->toContain('مؤلف الكتاب')
        ->and($description)->toContain(LibrarySection::Books->getLabel())
        ->and($description)->toStartWith('عنوان طويل جداً لكتاب');
});

it('labels the add button of the initiative goals in Arabic', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(CreateInitiative::class)
        ->assertSee(__('initiatives.fields.add_goal'))
        ->assertDontSee('إضافة إلى goals');
});

it('labels the add buttons of the guide steps and the site settings lists in Arabic', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));

    Livewire::test(CreateAssistantGuide::class)
        ->assertSee(__('assistant.admin.guides.add_step'))
        ->assertDontSee('إضافة إلى steps');

    // المتجر والتبرعات خلف PAYMENTS_ENABLED (مؤرشف)، وتسميتهما تُختبر مفعّلين
    config(['payments.enabled' => true]);
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(SiteSettings::class)
        ->assertSee(__('settings.home.add_stat'))
        ->assertSee(__('settings.store.add_product'))
        ->assertSee(__('settings.donations.add_amount'))
        ->assertDontSee('إضافة إلى home.stats')
        ->assertDontSee('إضافة إلى store.products')
        ->assertDontSee('إضافة إلى donations.amounts');
});
