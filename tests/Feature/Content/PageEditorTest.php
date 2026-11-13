<?php

use App\Actions\Content\PageEditor\EnsureEditablePage;
use App\Actions\Content\PageEditor\PublishPageDraft;
use App\Actions\Content\PageEditor\RevertPageRevision;
use App\Actions\Content\PageEditor\SavePageDraft;
use App\Actions\Content\SaveContent;
use App\Actions\Settings\UpdateHomeContent;
use App\Enums\ActivityEvent;
use App\Enums\PageRevisionStatus;
use App\Enums\RoleName;
use App\Filament\Pages\HomeContentPage;
use App\Filament\Resources\ContentPages\ContentPageResource;
use App\Filament\Resources\SitePages\Pages\EditSitePage;
use App\Filament\Resources\SitePages\RelationManagers\RevisionsRelationManager;
use App\Filament\Resources\SitePages\SitePageResource;
use App\Models\AssemblyMembership;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Setting;
use App\Models\User;
use App\Support\EditablePages;
use App\Support\HomeContent;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| «محرر الصفحات» (قرار صفوان، 31 أكتوبر 2026 — docs/decisions/2026-11-02-page-editor.md): صفحات الموقع الثابتة بمسودة ومعاينة
| ونشر ونسخ، للمسؤول التقني والمدير التنفيذي وحدهما (pages.edit_all).
*/

beforeEach(function () {
    Storage::fake('public');
    $this->admin = userWithRole(RoleName::TechnicalAdmin);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pageDraft(array $overrides = []): array
{
    return [
        'title' => 'برامج الجمعية العلمية',
        'summary' => 'وصف تجريبي للرأس',
        'image' => null,
        'blocks' => [
            ['type' => 'text', 'data' => ['heading' => 'القسم الأول', 'body' => '<p>نص القسم الأول</p>', 'visible' => true]],
            ['type' => 'text', 'data' => ['heading' => 'القسم الثاني', 'body' => '<p>نص القسم الثاني</p>', 'visible' => true]],
        ],
        ...$overrides,
    ];
}

function editablePage(User $actor, string $key = 'programs'): Page
{
    return app(EnsureEditablePage::class)->handle($actor, $key);
}

/**
 * صورة مرفوعة في مجلدها على القرص العام كما يتركها حقل الرفع قبل الإجراء.
 */
function uploadedPublicImage(string $directory): string
{
    $path = $directory.'/'.Str::random(20).'.png';
    Storage::disk('public')->put($path, imageBytes('png', 40, 20));

    return $path;
}

it('grants pages.edit_all to the technical officer and the executive director only', function () {
    foreach (RoleName::cases() as $role) {
        expect(userWithRole($role)->can('pages.edit_all'))
            ->toBe(in_array($role, [RoleName::TechnicalAdmin, RoleName::ExecutiveDirector], true), $role->value);
    }
});

it('opens the page editor to the two roles and refuses everyone else', function () {
    $page = editablePage($this->admin);

    foreach ([RoleName::TechnicalAdmin, RoleName::ExecutiveDirector] as $role) {
        $user = userWithRole($role);
        $this->actingAs($user)->get(SitePageResource::getUrl('index'))->assertOk();
        $this->actingAs($user)->get(SitePageResource::getUrl('edit', ['record' => $page]))->assertOk();
        $this->actingAs($user)->get(SitePageResource::getUrl('open', ['key' => 'contact']))->assertRedirect();
    }

    foreach ([RoleName::ContentEditor, RoleName::SuperAdmin, RoleName::BoardMember, RoleName::Secretary] as $role) {
        $user = userWithRole($role);
        $this->actingAs($user)->get(SitePageResource::getUrl('index'))->assertForbidden();
        $this->actingAs($user)->get(SitePageResource::getUrl('edit', ['record' => $page]))->assertForbidden();
        $this->actingAs($user)->get(SitePageResource::getUrl('open', ['key' => 'contact']))->assertForbidden();
    }

    auth()->logout();
    $this->get(SitePageResource::getUrl('index'))->assertRedirect();
    $this->actingAs($this->admin)->get(SitePageResource::getUrl('open', ['key' => 'unknown']))->assertNotFound();
});

it('lists every registered page with its original texts as the first revision, without changing the site', function () {
    Setting::put(HomeContent::KEY, ['page_heroes' => ['programs' => 'settings/programs-hero.png']]);

    $this->actingAs($this->admin)->get(SitePageResource::getUrl('index'))->assertOk();

    expect(Page::query()->whereIn('slug', EditablePages::slugs())->count())->toBe(count(EditablePages::PAGES));

    $page = Page::query()->where('slug', 'site-programs')->firstOrFail();
    $first = $page->revisions()->sole();

    expect($page->is_published)->toBeFalse()
        ->and($page->title)->toBe(__('programs.title'))
        ->and($first->status)->toBe(PageRevisionStatus::Archived)
        ->and($first->author_id)->toBeNull()
        ->and($first->content['title'])->toBe(__('programs.title'))
        ->and($first->content['image'])->toBe('settings/programs-hero.png');

    // ولا تكرار بفتح ثانٍ
    editablePage($this->admin);
    expect($page->revisions()->count())->toBe(1);

    $this->get('/programs')->assertOk()->assertDontSee('data-page-sections', false);
});

it('starts about from its existing published record', function () {
    Page::factory()->create(['slug' => Page::ABOUT, 'title' => 'عن الجمعية', 'summary' => 'وصف قائم', 'is_published' => true,
        'blocks' => [['type' => 'text', 'data' => ['heading' => 'من نحن', 'body' => '<p>نص قائم</p>']]]]);

    $page = editablePage($this->admin, 'about');
    $first = $page->revisions()->sole();

    expect($first->status)->toBe(PageRevisionStatus::Published)
        ->and($first->content['summary'])->toBe('وصف قائم')
        ->and($first->content['blocks'][0]['data']['heading'])->toBe('من نحن')
        ->and($first->content['blocks'][0]['data']['visible'])->toBeTrue();
});

it('saves a draft from the admin screen without touching the public page, and logs it', function () {
    $page = editablePage($this->admin);
    $this->actingAs($this->admin);

    Livewire::test(EditSitePage::class, ['record' => $page->getRouteKey()])
        ->fillForm(pageDraft())
        ->call('save')
        ->assertHasNoFormErrors();

    $draft = $page->revisions()->where('status', PageRevisionStatus::Draft->value)->sole();

    expect($draft->content['title'])->toBe('برامج الجمعية العلمية')
        ->and($draft->author_id)->toBe($this->admin->id)
        ->and($draft->content['blocks'])->toHaveCount(2)
        ->and($page->fresh()->is_published)->toBeFalse();

    $this->get('/programs')->assertOk()->assertDontSee('برامج الجمعية العلمية')->assertDontSee('نص القسم الأول');

    $log = Activity::query()->where('event', ActivityEvent::PageDraftSaved->value)->sole();
    expect($log->causer_id)->toBe($this->admin->id)
        ->and($log->subject_id)->toBe($page->id)
        ->and($log->properties['page'])->toBe('programs')
        ->and($log->properties['fields'])->toContain('title', 'summary', 'blocks');
});

it('records a reorder and a visibility change of the same sections', function () {
    $page = editablePage($this->admin);
    $save = app(SavePageDraft::class);
    $save->handle($this->admin, $page, pageDraft());

    $blocks = pageDraft()['blocks'];
    $save->handle($this->admin, $page, pageDraft(['blocks' => array_reverse($blocks)]));

    $reorder = Activity::query()->where('event', ActivityEvent::PageDraftSaved->value)->latest('id')->first();
    expect($reorder->properties['reordered'])->toBeTrue()
        ->and($reorder->properties['visibility_changed'])->toBeFalse()
        ->and($reorder->properties['fields'])->toBe(['blocks']);

    $blocks[0]['data']['visible'] = false;
    $save->handle($this->admin, $page, pageDraft(['blocks' => $blocks]));

    $toggle = Activity::query()->where('event', ActivityEvent::PageDraftSaved->value)->latest('id')->first();
    expect($toggle->properties['visibility_changed'])->toBeTrue()
        ->and($toggle->properties['reordered'])->toBeTrue()
        ->and($toggle->properties['hidden'])->toBe(1);

    // مسودة واحدة للصفحة مهما تكرر الحفظ
    expect($page->revisions()->where('status', PageRevisionStatus::Draft->value)->count())->toBe(1);
});

it('publishes the draft: title, summary, visible sections in order and the header image, and logs it', function () {
    $page = editablePage($this->admin);
    $hero = uploadedPublicImage('settings');
    $blocks = pageDraft()['blocks'];
    $blocks[] = ['type' => 'text', 'data' => ['heading' => 'قسم مخفي', 'body' => '<p>لا يظهر</p>', 'visible' => false]];

    app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['image' => $hero, 'blocks' => array_reverse($blocks)]));
    $revision = app(PublishPageDraft::class)->handle($this->admin, $page);

    expect($revision->status)->toBe(PageRevisionStatus::Published)
        ->and($revision->published_by)->toBe($this->admin->id)
        ->and($page->fresh()->is_published)->toBeTrue()
        ->and(Setting::group(HomeContent::KEY)['page_heroes']['programs'])->toBe($hero)
        ->and($page->revisions()->where('status', PageRevisionStatus::Published->value)->count())->toBe(1);

    $this->get('/programs')->assertOk()
        ->assertSee('<title>برامج الجمعية العلمية', false)
        ->assertSee('وصف تجريبي للرأس')
        ->assertSeeInOrder(['القسم الثاني', 'القسم الأول'])
        ->assertDontSee('قسم مخفي')
        ->assertSee(Storage::disk('public')->url($hero), false);

    expect(Activity::query()->where('event', ActivityEvent::PagePublished->value)->where('subject_id', $page->id)->exists())->toBeTrue();

    // نشر ثانٍ يجعل المنشورة سابقة
    app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['title' => 'عنوان ثانٍ']));
    app(PublishPageDraft::class)->handle($this->admin, $page);

    expect($revision->fresh()->status)->toBe(PageRevisionStatus::Archived);
    $this->get('/programs')->assertSee('عنوان ثانٍ');
});

it('refuses to publish without a draft', function () {
    $page = editablePage($this->admin);

    expect(fn () => app(PublishPageDraft::class)->handle($this->admin, $page))->toThrow(ValidationException::class);
});

it('publishes from the admin screen action', function () {
    $page = editablePage($this->admin);
    app(SavePageDraft::class)->handle($this->admin, $page, pageDraft());
    $this->actingAs($this->admin);

    Livewire::test(EditSitePage::class, ['record' => $page->getRouteKey()])
        ->callAction(TestAction::make('publish'))
        ->assertHasNoActionErrors();

    expect($page->fresh()->is_published)->toBeTrue();
});

it('reverts to a previous version as a new draft, keeping the current draft in the history', function () {
    $page = editablePage($this->admin);
    app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['title' => 'الأولى']));
    $first = app(PublishPageDraft::class)->handle($this->admin, $page);
    app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['title' => 'الثانية']));
    app(PublishPageDraft::class)->handle($this->admin, $page);
    $pending = app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['title' => 'مسودة قائمة']));

    $director = userWithRole(RoleName::ExecutiveDirector);
    $draft = app(RevertPageRevision::class)->handle($director, $first->fresh());

    expect($draft->status)->toBe(PageRevisionStatus::Draft)
        ->and($draft->reverted_from_id)->toBe($first->id)
        ->and($draft->author_id)->toBe($director->id)
        ->and($draft->content['title'])->toBe('الأولى')
        ->and($pending->fresh()->status)->toBe(PageRevisionStatus::Archived);

    // لا يتغير المعروض قبل النشر
    $this->get('/programs')->assertSee('الثانية');

    app(PublishPageDraft::class)->handle($director, $page);
    $this->get('/programs')->assertSee('الأولى');

    $log = Activity::query()->where('event', ActivityEvent::PageReverted->value)->sole();
    expect($log->causer_id)->toBe($director->id)->and($log->properties['from'])->toBe($first->id);

    $current = app(SavePageDraft::class)->handle($director, $page, pageDraft());
    expect(fn () => app(RevertPageRevision::class)->handle($director, $current))->toThrow(ValidationException::class);
});

it('refuses every action to other roles, wherever it is called from', function (RoleName $role) {
    $page = editablePage($this->admin);
    app(SavePageDraft::class)->handle($this->admin, $page, pageDraft());
    $draft = $page->revisions()->where('status', PageRevisionStatus::Draft->value)->sole();
    $user = userWithRole($role);

    expect(fn () => app(EnsureEditablePage::class)->handle($user, 'contact'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SavePageDraft::class)->handle($user, $page, pageDraft(['title' => 'اختراق'])))->toThrow(AuthorizationException::class)
        ->and(fn () => app(PublishPageDraft::class)->handle($user, $page))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RevertPageRevision::class)->handle($user, $page->revisions()->oldest('id')->first()))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveContent::class)->handle($user, $page, ['title' => 'اختراق']))->toThrow(AuthorizationException::class);

    expect($draft->fresh()->content['title'])->toBe('برامج الجمعية العلمية')
        ->and($page->fresh()->is_published)->toBeFalse();
})->with([RoleName::ContentEditor, RoleName::SuperAdmin, RoleName::BoardMember, RoleName::Member]);

it('rejects invalid input', function (array $overrides) {
    $page = editablePage($this->admin);

    expect(fn () => app(SavePageDraft::class)->handle($this->admin, $page, pageDraft($overrides)))->toThrow(ValidationException::class);
    expect($page->revisions()->where('status', PageRevisionStatus::Draft->value)->exists())->toBeFalse();
})->with([
    'empty title' => [['title' => '']],
    'long title' => [['title' => str_repeat('ع', 161)]],
    'control characters' => [['summary' => "نص\u{202E}مقلوب"]],
    'unknown section type' => [['blocks' => [['type' => 'script', 'data' => ['body' => 'x']]]]],
    'text without body' => [['blocks' => [['type' => 'text', 'data' => ['heading' => 'بلا نص']]]]],
    'too many sections' => [['blocks' => array_fill(0, 31, ['type' => 'text', 'data' => ['body' => '<p>x</p>']])]],
    'unknown icon' => [['blocks' => [['type' => 'cards', 'data' => ['items' => [['title' => 'بطاقة', 'icon' => '../../etc/passwd']]]]]]],
    'non-boolean visibility' => [['blocks' => [['type' => 'text', 'data' => ['body' => '<p>x</p>', 'visible' => 'maybe']]]]],
]);

it('rejects an image outside its folder or one that is not really an image, and deletes the latter', function () {
    $page = editablePage($this->admin);

    expect(fn () => app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['image' => 'news/someone-else.png'])))->toThrow(ValidationException::class);

    $fake = 'pages/'.Str::random(20).'.png';
    Storage::disk('public')->put($fake, '<?php echo "not an image";');

    expect(fn () => app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['blocks' => [['type' => 'image', 'data' => ['picture' => $fake]]]])))->toThrow(ValidationException::class);
    Storage::disk('public')->assertMissing($fake);
});

it('rejects an oversized or wrong-type header image in the admin form', function (UploadedFile $file) {
    $page = editablePage($this->admin);
    $this->actingAs($this->admin);

    Livewire::test(EditSitePage::class, ['record' => $page->getRouteKey()])
        ->fillForm(pageDraft(['image' => [$file]]))
        ->call('save')
        ->assertHasFormErrors(['image']);

    expect($page->revisions()->where('status', PageRevisionStatus::Draft->value)->exists())->toBeFalse();
})->with([
    'too large' => [fn () => UploadedFile::fake()->image('big.jpg', 100, 100)->size(7000)],
    'svg' => [fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
    'pdf' => [fn () => UploadedFile::fake()->create('file.pdf', 10, 'application/pdf')],
]);

it('accepts a real uploaded header image, cleans it, and keeps it after a later replacement for revert', function () {
    $page = editablePage($this->admin);
    $this->actingAs($this->admin);

    Livewire::test(EditSitePage::class, ['record' => $page->getRouteKey()])
        ->fillForm(pageDraft(['image' => [UploadedFile::fake()->createWithContent('hero.png', imageBytes('png', 60, 30))]]))
        ->call('save')
        ->assertHasNoFormErrors();

    $first = $page->revisions()->where('status', PageRevisionStatus::Draft->value)->sole()->content['image'];
    expect($first)->toStartWith('settings/');
    Storage::disk('public')->assertExists($first);

    app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['image' => uploadedPublicImage('settings')]));
    app(PublishPageDraft::class)->handle($this->admin, $page);

    Storage::disk('public')->assertExists($first);
});

it('previews a draft through a short signed link for the two roles only, never indexed or cached', function () {
    $page = editablePage($this->admin);
    $draft = app(SavePageDraft::class)->handle($this->admin, $page, pageDraft());
    $url = $draft->previewUrl();

    $this->actingAs($this->admin)->get($url)->assertOk()
        ->assertSee('برامج الجمعية العلمية')
        ->assertSee('نص القسم الأول')
        ->assertSee('data-page-preview-banner', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeaderContains('Cache-Control', 'no-store');

    $this->actingAs(userWithRole(RoleName::ExecutiveDirector))->get($url)->assertOk();
    $this->actingAs(userWithRole(RoleName::ContentEditor))->get($url)->assertForbidden();
    $this->actingAs(userWithRole(RoleName::SuperAdmin))->get($url)->assertForbidden();

    // رابط منتهٍ، أو مزوّر برقم نسخة أخرى، أو بلا توقيع
    $expired = URL::temporarySignedRoute('pages.preview', now()->subMinute(), ['revision' => $draft->id]);
    $this->actingAs($this->admin)->get($expired)->assertForbidden();
    $other = $page->revisions()->oldest('id')->firstOrFail();
    $this->actingAs($this->admin)->get(str_replace('/page-preview/'.$draft->id, '/page-preview/'.$other->id, $url))->assertForbidden();
    $this->actingAs($this->admin)->get(route('pages.preview', ['revision' => $draft->id]))->assertForbidden();

    auth()->logout();
    $this->get($url)->assertRedirect();
});

it('shows the edit button to the two roles only, per request, never in the cached page text', function () {
    $page = editablePage($this->admin);
    app(SavePageDraft::class)->handle($this->admin, $page, pageDraft());
    app(PublishPageDraft::class)->handle($this->admin, $page);
    $open = SitePageResource::getUrl('open', ['key' => 'programs']);

    // يُطلب أولاً من حساب المسؤول التقني فتُملأ ذاكرة المحتوى
    $this->actingAs($this->admin)->get('/programs')->assertOk()
        ->assertSee('data-page-edit-button', false)
        ->assertSee($open, false);
    $this->actingAs(userWithRole(RoleName::ExecutiveDirector))->get('/programs')->assertSee('data-page-edit-button', false);
    $this->actingAs($this->admin)->get('/')->assertSee(HomeContentPage::getUrl(), false);

    foreach ([RoleName::ContentEditor, RoleName::SuperAdmin, RoleName::Member] as $role) {
        $this->actingAs(userWithRole($role))->get('/programs')->assertOk()->assertDontSee('data-page-edit-button', false);
    }

    auth()->logout();
    $this->get('/programs')->assertOk()->assertSee('نص القسم الأول')->assertDontSee('data-page-edit-button', false)->assertDontSee($open, false);
    $this->get('/')->assertDontSee('data-page-edit-button', false);

    // والمخزَّن من نص الأقسام بلا زر
    expect(EditablePages::live('programs.index')['html'])->not->toContain('data-page-edit-button');
});

it('keeps the fixed pages out of the general pages screen and reserves their slugs', function () {
    $page = editablePage($this->admin);
    $editor = userWithRole(RoleName::ContentEditor);

    $this->actingAs($editor)->get(ContentPageResource::getUrl('edit', ['record' => $page]))->assertNotFound();

    expect(fn () => app(SaveContent::class)->handle($editor, new Page, ['slug' => 'site-contact', 'title' => 'سطو']))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveContent::class)->handle($editor, Page::factory()->create(['slug' => 'other']), ['slug' => 'site-library']))->toThrow(ValidationException::class);
});

it('redirects the fixed page record link to its template and leaves it out of the sitemap', function () {
    $page = editablePage($this->admin);
    app(SavePageDraft::class)->handle($this->admin, $page, pageDraft());
    app(PublishPageDraft::class)->handle($this->admin, $page);

    $this->get('/pages/site-programs')->assertRedirect(route('programs.index'));
    $this->get('/sitemap.xml')->assertOk()->assertDontSee('site-programs');
});

it('does not let a home content save wipe the header images owned by the page editor', function () {
    $page = editablePage($this->admin);
    $hero = uploadedPublicImage('settings');
    app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['image' => $hero]));
    app(PublishPageDraft::class)->handle($this->admin, $page);

    $this->actingAs($this->admin);
    Livewire::test(HomeContentPage::class)->call('save')->assertHasNoErrors();
    app(UpdateHomeContent::class)->handle($this->admin, ['page_heroes' => ['programs' => null]]);

    expect(Setting::group(HomeContent::KEY)['page_heroes']['programs'])->toBe($hero);
    Storage::disk('public')->assertExists($hero);
});

it('lists the revisions with their authors on the edit screen and refuses editing a revision', function () {
    $page = editablePage($this->admin);
    $draft = app(SavePageDraft::class)->handle($this->admin, $page, pageDraft());

    expect($this->admin->can('update', $draft))->toBeFalse()
        ->and($this->admin->can('delete', $draft))->toBeFalse()
        ->and($this->admin->can('revert', $draft))->toBeTrue()
        ->and(userWithRole(RoleName::SuperAdmin)->can('viewAny', PageRevision::class))->toBeFalse();

    $this->actingAs($this->admin);

    Livewire::test(RevisionsRelationManager::class, ['ownerRecord' => $page, 'pageClass' => EditSitePage::class])
        ->assertOk()
        ->assertCanSeeTableRecords($page->revisions()->get())
        ->assertSee($this->admin->name)
        ->assertSee(__('page_editor.revisions.original'))
        ->assertTableActionHidden('revert', $draft)
        ->assertTableActionVisible('revert', $page->revisions()->oldest('id')->first());
});

it('does not let the content editor move a page editor image into a home content slot and delete it', function () {
    $page = editablePage($this->admin);
    $hero = uploadedPublicImage('settings');
    app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['image' => $hero]));
    app(PublishPageDraft::class)->handle($this->admin, $page);

    $editor = userWithRole(RoleName::ContentEditor);

    expect(fn () => app(UpdateHomeContent::class)->handle($editor, ['hero_slides' => ['about' => $hero]]))->toThrow(ValidationException::class);
    app(UpdateHomeContent::class)->handle($editor, ['hero_slides' => ['about' => null]]);

    Storage::disk('public')->assertExists($hero);
    expect(Setting::group(HomeContent::KEY)['page_heroes']['programs'])->toBe($hero);
});

it('refuses to attach an image that belongs to another page', function () {
    $other = editablePage($this->admin, 'contact');
    $picture = uploadedPublicImage('pages');
    app(SavePageDraft::class)->handle($this->admin, $other, pageDraft(['blocks' => [['type' => 'image', 'data' => ['picture' => $picture]]]]));

    $page = editablePage($this->admin);

    expect(fn () => app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['blocks' => [['type' => 'image', 'data' => ['picture' => $picture]]]])))
        ->toThrow(ValidationException::class);
    Storage::disk('public')->assertExists($picture);
});

it('treats a falsy visibility value as hidden', function (mixed $value) {
    $page = editablePage($this->admin);
    $draft = app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['blocks' => [['type' => 'text', 'data' => ['body' => '<p>مخفي</p>', 'visible' => $value]]]]));

    expect($draft->content['blocks'][0]['data']['visible'])->toBeFalse();
})->with([0, '0', false]);

it('creates the first revision when the edit screen is opened directly', function () {
    $about = Page::factory()->create(['slug' => Page::ABOUT, 'title' => 'عن الجمعية', 'is_published' => true]);
    $this->actingAs($this->admin);

    Livewire::test(EditSitePage::class, ['record' => $about->getRouteKey()])->assertOk();

    expect($about->revisions()->count())->toBe(1)
        ->and(Activity::query()->where('description', 'page_revision_initial')->where('subject_id', $about->id)->exists())->toBeTrue();
});

it('publishes about with its visible sections only', function () {
    Page::factory()->create(['slug' => Page::ABOUT, 'title' => 'عن الجمعية', 'is_published' => false]);
    $page = editablePage($this->admin, 'about');
    $blocks = pageDraft()['blocks'];
    $blocks[1]['data']['visible'] = false;

    app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['title' => 'عن جمعية إعجاز', 'blocks' => $blocks]));
    app(PublishPageDraft::class)->handle($this->admin, $page);

    $this->get('/about')->assertOk()->assertSee('عن جمعية إعجاز')->assertSee('نص القسم الأول')->assertDontSee('نص القسم الثاني');
});

/*
| متابعة مراجعة المشرف على #124 (fix/v0.15-review-lows-2)
*/

it('reads the revision images of every page with one query that returns paths only', function () {
    $page = editablePage($this->admin);
    $other = editablePage($this->admin, 'contact');
    $hero = uploadedPublicImage('settings');
    $picture = uploadedPublicImage('pages');
    $card = uploadedPublicImage('pages');
    $elsewhere = uploadedPublicImage('pages');

    app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['image' => $hero, 'blocks' => [
        ['type' => 'image', 'data' => ['picture' => $picture]],
        ['type' => 'cards', 'data' => ['items' => [['title' => 'بطاقة', 'picture' => $card]]]],
    ]]));
    app(SavePageDraft::class)->handle($this->admin, $other, pageDraft(['blocks' => [['type' => 'image', 'data' => ['picture' => $elsewhere]]]]));

    DB::flushQueryLog();
    DB::enableQueryLog();
    $owned = EditablePages::ownedImages([]);
    $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'page_revisions'));
    DB::disableQueryLog();

    expect($owned)->toEqualCanonicalizing([$hero, $picture, $card, $elsewhere])
        ->and($queries)->toHaveCount(1)
        ->and($queries->first())->not->toContain('select *')
        ->and(EditablePages::knownImages($page))->toEqualCanonicalizing([$hero, $picture, $card])
        ->and(EditablePages::foreignImages($page))->toContain($elsewhere)->not->toContain($picture);
});

it('keeps only the known fields of each section type in the draft', function () {
    $page = editablePage($this->admin);

    $draft = app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['blocks' => [
        ['type' => 'text', 'data' => ['heading' => 'عنوان', 'body' => '<p>نص</p>', 'extra' => 'لا يُحفظ', 'picture' => 'pages/x.png']],
        ['type' => 'cards', 'data' => ['items' => [['title' => 'بطاقة', 'secret' => 'لا يُحفظ']]]],
    ]]));

    expect(array_keys($draft->content['blocks'][0]['data']))->toEqualCanonicalizing(['visible', 'heading', 'body'])
        ->and(array_keys($draft->content['blocks'][1]['data']))->toEqualCanonicalizing(['visible', 'items'])
        ->and(array_keys($draft->content['blocks'][1]['data']['items'][0]))->toBe(['title']);
});

it('refuses to attach the site settings image to a page', function () {
    $image = uploadedPublicImage('settings');
    Setting::put('home', ['hero_image' => $image]);
    $page = editablePage($this->admin);

    expect(fn () => app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['image' => $image])))
        ->toThrow(ValidationException::class);
    Storage::disk('public')->assertExists($image);
});

it('creates the page records on the first list visit only, never again on a later GET', function () {
    $counts = fn (): array => [Page::query()->count(), PageRevision::query()->count(), Activity::query()->where('description', 'page_revision_initial')->count()];
    expect($counts())->toBe([0, 0, 0]);

    $this->actingAs($this->admin)->get(SitePageResource::getUrl('index'))->assertOk();
    $before = $counts();
    expect($before)->toBe(array_fill(0, 3, count(EditablePages::PAGES)));

    $this->get(SitePageResource::getUrl('index'))->assertOk();
    $this->get(SitePageResource::getUrl('open', ['key' => 'programs']))->assertRedirect();

    expect($counts())->toBe($before);
});

it('previews a draft without a header image over the neutral image of its own page', function () {
    $page = editablePage($this->admin, 'governance');
    $draft = app(SavePageDraft::class)->handle($this->admin, $page, pageDraft());

    $this->actingAs($this->admin)->get($draft->previewUrl())->assertOk()
        ->assertSee(asset('images/placeholders/pages/governance.svg'), false)
        ->assertDontSee(asset('images/placeholders/pages/default.svg'), false);
});

it('keeps the published assembly page title off the members portal', function () {
    $page = editablePage($this->admin, 'assembly');
    app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['title' => 'عنوان الجمعية المنشور']));
    app(PublishPageDraft::class)->handle($this->admin, $page);

    $this->get('/assembly')->assertOk()->assertSee('<title>عنوان الجمعية المنشور', false);

    $member = AssemblyMembership::factory()->create()->user;
    $this->actingAs($member)->get('/assembly')->assertOk()
        ->assertSee('data-board-portal', false)
        ->assertSee('<title>'.__('assembly.title'), false)
        ->assertDontSee('عنوان الجمعية المنشور');

    // ولا زر «تعديل هذه الصفحة» على البوابة لمحرر عضو، لأنها ليست الصفحة التي يفتحها
    $editor = userWithRole(RoleName::TechnicalAdmin);
    AssemblyMembership::factory()->for($editor)->create();
    $this->actingAs($editor)->get('/assembly')->assertOk()
        ->assertSee('data-board-portal', false)
        ->assertDontSee('data-page-edit-button', false);
});

it('strips scripts and javascript links from a published section', function () {
    $page = editablePage($this->admin);
    app(SavePageDraft::class)->handle($this->admin, $page, pageDraft(['blocks' => [
        ['type' => 'text', 'data' => ['heading' => 'قسم', 'body' => '<p>نص آمن</p><script>alert(1)</script><p><a href="javascript:alert(2)">رابط</a></p>']],
    ]]));
    app(PublishPageDraft::class)->handle($this->admin, $page);

    $html = $this->get('/programs')->assertOk()->assertSee('نص آمن')->getContent();

    expect($html)->not->toContain('<script>alert(1)')->not->toContain('javascript:alert(2)');
});

it('tells the editor that an empty summary keeps the approved text on a page with no original summary (review on #247)', function () {
    $this->actingAs($this->admin);

    $contact = app(EnsureEditablePage::class)->handle($this->admin, 'contact');
    $services = app(EnsureEditablePage::class)->handle($this->admin, 'services');

    Livewire::test(EditSitePage::class, ['record' => $contact->getRouteKey()])->assertSee(__('page_editor.fields.summary_hint_template'));
    Livewire::test(EditSitePage::class, ['record' => $services->getRouteKey()])
        ->assertSee(__('page_editor.fields.summary_hint'))
        ->assertDontSee(__('page_editor.fields.summary_hint_template'));
});
