<?php

use App\Actions\Content\SaveContent;
use App\Enums\RoleName;
use App\Filament\Resources\Albums\AlbumResource;
use App\Filament\Resources\Albums\Pages\CreateAlbum;
use App\Filament\Resources\Albums\Pages\ListAlbums;
use App\Filament\Resources\NewsArticles\NewsArticleResource;
use App\Filament\Resources\NewsArticles\Pages\CreateNewsArticle;
use App\Filament\Resources\NewsArticles\Pages\ListNewsArticles;
use App\Filament\Resources\Partners\Pages\CreatePartner;
use App\Filament\Resources\Partners\Pages\ListPartners;
use App\Filament\Resources\Partners\PartnerResource;
use App\Models\Album;
use App\Models\NewsArticle;
use App\Models\Partner;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('public');
    $this->editor = userWithRole(RoleName::ContentEditor);
});

it('opens the media screens to the editor, lets the board look only, and keeps others out', function (string $resource) {
    $this->actingAs($this->editor)->get($resource::getUrl('index'))->assertOk();
    $this->actingAs($this->editor)->get($resource::getUrl('create'))->assertOk();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get($resource::getUrl('index'))->assertOk();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get($resource::getUrl('create'))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Secretary))->get($resource::getUrl('index'))->assertForbidden();
})->with([NewsArticleResource::class, AlbumResource::class, PartnerResource::class]);

it('lets the board neither edit nor delete media content', function (string $resource, string $list, string $model) {
    $record = $model::factory()->create();
    $this->actingAs(userWithRole(RoleName::BoardMember));

    $this->get($resource::getUrl('edit', ['record' => $record]))->assertForbidden();
    Livewire::test($list)->assertActionHidden(TestAction::make('delete')->table($record));

    expect($model::query()->whereKey($record->getKey())->exists())->toBeTrue();
})->with([
    'news' => [NewsArticleResource::class, ListNewsArticles::class, NewsArticle::class],
    'albums' => [AlbumResource::class, ListAlbums::class, Album::class],
    'partners' => [PartnerResource::class, ListPartners::class, Partner::class],
]);

it('creates an article with its cover, content and category, logs it and shows it on the site at once', function () {
    $this->actingAs($this->editor);

    Livewire::test(CreateNewsArticle::class)
        ->fillForm([
            'title' => 'الملتقى السنوي',
            'slug' => 'annual-forum',
            'published_on' => '2026-02-01',
            'category' => 'فعاليات',
            'summary' => 'ملخص الخبر',
            'image' => [UploadedFile::fake()->image('cover.jpg', 800, 500)],
            'blocks' => [['type' => 'text', 'data' => ['heading' => 'تفاصيل', 'body' => '<p>نص الخبر</p>']]],
            'is_published' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = NewsArticle::where('slug', 'annual-forum')->sole();

    expect($article->image)->toStartWith('news/')
        ->and(Storage::disk('public')->exists((string) $article->image))->toBeTrue()
        ->and(Activity::whereMorphedTo('subject', $article)->where('event', 'created')->exists())->toBeTrue();

    $this->get('/media/news/annual-forum')->assertOk()->assertSee('نص الخبر');
    $this->get('/media/news?category=فعاليات')->assertSee('الملتقى السنوي');
});

it('deletes an article with all its images', function () {
    Storage::disk('public')->put('news/cover.jpg', 'x');
    Storage::disk('public')->put('news/gallery.jpg', 'x');
    $article = NewsArticle::factory()->create(['image' => 'news/cover.jpg', 'gallery' => ['news/gallery.jpg']]);
    $this->actingAs($this->editor);

    Livewire::test(ListNewsArticles::class)->callAction(TestAction::make('delete')->table($article));

    expect(NewsArticle::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles('news'))->toBe([]);
});

it('refuses to publish an album without photos, and stores the photos of a new album', function () {
    $this->actingAs($this->editor);
    $album = ['title' => 'ألبوم الملتقى', 'slug' => 'forum', 'published_on' => '2026-02-01', 'is_published' => true];

    Livewire::test(CreateAlbum::class)
        ->fillForm($album)
        ->call('create')
        ->assertHasFormErrors(['photos' => 'required']);

    Livewire::test(CreateAlbum::class)
        ->fillForm([...$album, 'photos' => [UploadedFile::fake()->image('one.jpg', 800, 500), UploadedFile::fake()->image('two.jpg', 800, 500)]])
        ->call('create')
        ->assertHasNoFormErrors();

    $saved = Album::where('slug', 'forum')->sole();

    expect($saved->photoList())->toHaveCount(2)
        ->and($saved->cover())->toStartWith('albums/');

    $this->get('/media/albums/forum')->assertOk();
});

it('accepts a partner with an https link and a picture logo, and refuses unsafe links and SVG logos', function (array $data, array $errors) {
    $this->actingAs($this->editor);

    Livewire::test(CreatePartner::class)
        ->fillForm(['name' => 'شريك', 'logo' => [UploadedFile::fake()->image('logo.png', 400, 200)], 'is_published' => true, ...$data])
        ->call('create')
        ->assertHasFormErrors($errors);

    expect(Partner::count())->toBe(0);
})->with([
    'http' => [['url' => 'http://partner.example'], ['url']],
    'script' => [['url' => 'javascript:alert(1)'], ['url']],
    'user before host' => [['url' => 'https://ejaz.sa@evil.example'], ['url']],
    'svg logo' => [['logo' => [UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')]], ['logo']],
]);

// والشعار اختياري: الشريك بلا شعار يُنشر باسمه (قرار صفوان، 11 نوفمبر 2026، القرار ٣ — decisions/2026-11-11-fix-panel-gaps-nov11.md)
it('accepts a published partner without a logo', function () {
    $this->actingAs($this->editor);

    Livewire::test(CreatePartner::class)
        ->fillForm(['name' => 'شريك', 'logo' => null, 'is_published' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Partner::query()->sole()->logo)->toBeNull();
});

it('never publishes an album without photos, even when the action is called directly', function () {
    expect(fn () => app(SaveContent::class)->handle($this->editor, new Album, ['title' => 'ألبوم', 'slug' => 'empty', 'published_on' => '2026-02-01', 'is_published' => true]))
        ->toThrow(ValidationException::class, __('albums.validation.needs_photos'));

    // ويُحفظ مسودة بلا صور
    app(SaveContent::class)->handle($this->editor, new Album, ['title' => 'ألبوم', 'slug' => 'empty', 'published_on' => '2026-02-01', 'is_published' => false]);

    expect(Album::count())->toBe(1);
});

it('refuses a photo of another album, and deletes a photo removed from an album', function () {
    foreach (['other', 'kept', 'removed'] as $name) {
        Storage::disk('public')->put("albums/{$name}.jpg", 'x');
    }

    Album::factory()->create(['photos' => ['albums/other.jpg']]);
    $album = Album::factory()->create(['photos' => ['albums/kept.jpg', 'albums/removed.jpg']]);

    expect(fn () => app(SaveContent::class)->handle($this->editor, $album, ['photos' => ['albums/kept.jpg', 'albums/other.jpg']]))
        ->toThrow(ValidationException::class, __('validation.image_in_use'));

    app(SaveContent::class)->handle($this->editor, $album->fresh(), ['photos' => ['albums/kept.jpg']]);

    expect(Storage::disk('public')->exists('albums/removed.jpg'))->toBeFalse()
        ->and(Storage::disk('public')->exists('albums/kept.jpg'))->toBeTrue()
        ->and(Storage::disk('public')->exists('albums/other.jpg'))->toBeTrue();
});

it('keeps the news categories tidy: trimmed, and none when left empty', function () {
    $save = fn (string $category) => app(SaveContent::class)->handle($this->editor, new NewsArticle, [
        'title' => 'خبر', 'slug' => 'news-'.md5($category), 'published_on' => '2026-02-01', 'category' => $category,
    ]);

    expect($save('  فعاليات  ')->category)->toBe('فعاليات')
        ->and($save('   ')->category)->toBeNull()
        ->and(NewsArticle::categoriesIn(NewsArticle::query()))->toBe(['فعاليات']);
});

it('accepts a partner without a link and shows it without one', function () {
    $this->actingAs($this->editor);

    Livewire::test(CreatePartner::class)
        ->fillForm(['name' => 'شريك بلا رابط', 'logo' => [UploadedFile::fake()->image('logo.png', 400, 200)], 'is_published' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Partner::where('name', 'شريك بلا رابط')->sole()->url)->toBeNull();
    $this->get('/media/partners')->assertSee('شريك بلا رابط');
});

it('creates a partner at the end of the order and shows it on the site', function () {
    Partner::factory()->create(['sort' => 5]);
    $this->actingAs($this->editor);

    Livewire::test(CreatePartner::class)
        ->fillForm(['name' => 'شريك جديد', 'url' => 'https://partner.example', 'logo' => [UploadedFile::fake()->image('logo.png', 400, 200)], 'is_published' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Partner::where('name', 'شريك جديد')->sole()->sort)->toBe(6);
    $this->get('/media/partners')->assertSee('شريك جديد')->assertSee('href="https://partner.example"', false);
});

it('records the order of the partners and shows it on the site at once, but not for the board', function () {
    $first = Partner::factory()->create(['name' => 'الأول', 'sort' => 1]);
    $second = Partner::factory()->create(['name' => 'الثاني', 'sort' => 2]);
    $this->get('/media/partners')->assertSeeInOrder(['الأول', 'الثاني']);

    $this->actingAs(userWithRole(RoleName::BoardMember));
    Livewire::test(ListPartners::class)->call('reorderTable', [(string) $second->id, (string) $first->id]);
    expect($first->fresh()->sort)->toBe(1);

    $this->actingAs($this->editor);
    Livewire::test(ListPartners::class)->call('reorderTable', [(string) $second->id, (string) $first->id]);

    $this->get('/media/partners')->assertSeeInOrder(['الثاني', 'الأول']);
    expect(Activity::where('event', 'content_reordered')->exists())->toBeTrue();
});

it('refuses to save media content for someone without the permission, wherever it is called from', function (string $model) {
    expect(fn () => app(SaveContent::class)->handle(userWithRole(RoleName::BoardMember), new $model, ['title' => 'x', 'name' => 'x']))
        ->toThrow(AuthorizationException::class);
})->with([NewsArticle::class, Album::class, Partner::class]);
