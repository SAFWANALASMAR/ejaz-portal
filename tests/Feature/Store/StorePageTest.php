<?php

use App\Actions\Store\SaveStoreItem;
use App\Contracts\StoreCatalog;
use App\Enums\RoleName;
use App\Enums\StoreItemCategory;
use App\Filament\Resources\StoreItems\Pages\CreateStoreItem;
use App\Filament\Resources\StoreItems\Pages\EditStoreItem;
use App\Filament\Resources\StoreItems\Pages\ListStoreItems;
use App\Filament\Resources\StoreItems\StoreItemResource;
use App\Models\MenuItem;
use App\Models\StoreItem;
use App\Services\Store\FakeStoreCatalog;
use App\Services\Store\ManualStoreCatalog;
use App\Support\MenuSections;
use App\Support\Store\StoreProduct;
use Database\Seeders\DemoStoreItemsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| «المتجر والتبرعات» (قرار صفوان، 4 نوفمبر 2026 — docs/decisions/2026-11-04-store-page.md): صفحة /store في الموقع بدل تحويلها
| إلى سلة، بعناصر من اللوحة (store_items.*)، وأزرار إلى متجر سلة من الكتالوج (StoreCatalog::purchaseUrl) في تبويب جديد.
*/

beforeEach(function () {
    Storage::fake('public');
});

it('shows the published items only, in their panel order, with outbound buttons in a new tab', function () {
    StoreItem::factory()->create(['title' => 'تبرع ثانٍ', 'sort' => 2, 'salla_url' => 'https://ejaz.org.sa/p2']);
    StoreItem::factory()->publication()->create(['title' => 'إصدار أول', 'sort' => 1, 'salla_url' => 'https://ejaz.org.sa/p1']);
    StoreItem::factory()->unpublished()->create(['title' => 'مسودة المتجر']);

    $html = (string) $this->get('/store')->assertOk()
        ->assertSee(__('store.page.title'))
        ->assertSeeInOrder(['إصدار أول', 'تبرع ثانٍ'])
        ->assertDontSee('مسودة المتجر')
        ->getContent();

    $grid = (string) str($html)->after('data-store-grid')->before('</ul>');

    expect($grid)->toContain('href="https://ejaz.org.sa/p1" target="_blank" rel="noopener noreferrer"')
        ->toContain('href="https://ejaz.org.sa/p2" target="_blank" rel="noopener noreferrer"')
        ->toContain(__('store.page.actions.donation'))
        ->toContain(__('store.page.actions.publication'))
        ->not->toContain('wire:navigate');
});

it('no longer redirects /store to Salla', function () {
    $this->get('/store')->assertOk()->assertSee('data-store-page', false);
});

it('shows no payment note line on the store page, only the enter-store button', function () {
    $html = (string) $this->get('/store')->assertOk()
        ->assertDontSee('الدفع يتم في متجر الجمعية')
        ->assertDontSee('store.page.payment_note')
        ->getContent();

    // الزر باقٍ بنصه المخفي لقارئ الشاشة «يُفتح في تبويب جديد»
    expect(preg_match('#<a\b[^>]*\bdata-store-enter\b[^>]*>.*?</a>#s', $html, $button))->toBe(1)
        ->and($button[0])->toContain(__('store.page.opens_in_new_tab'));
});

it('filters by category tab, marks the active tab, and sends an unknown category back to all', function () {
    StoreItem::factory()->create(['title' => 'سهم تبرع']);
    StoreItem::factory()->publication()->create(['title' => 'كتاب الجمعية']);

    $this->get('/store?category=donation')->assertOk()->assertSee('سهم تبرع')->assertDontSee('كتاب الجمعية');

    $html = (string) $this->get('/store?category=publication')->assertOk()->assertSee('كتاب الجمعية')->assertDontSee('سهم تبرع')->getContent();
    expect($html)->toMatch('#href="'.preg_quote(route('store.index', ['category' => 'publication']), '#').'"[^>]*aria-current="page"#');

    $this->get('/store?category=other')->assertRedirect(route('store.index'));
    $this->get('/store?category[]=donation')->assertRedirect(route('store.index'));
});

// ملاحظة المشرف على اللقطات الأولى: تبويب التصنيف لا يظهر إلا وفيه عنصر منشور، و«الكل» دائماً
it('shows a category tab only once a published item is in it, and sends an empty category back to all', function () {
    StoreItem::factory()->create(['title' => 'سهم تبرع']);
    StoreItem::factory()->publication()->unpublished()->create(['title' => 'كتاب مسودة']);
    $tabs = fn (): string => (string) str($this->get('/store')->assertOk()->getContent())->after('class="tabs-bar')->before('</nav>');

    expect($tabs())->toContain(__('store.page.tabs.all'))
        ->toContain(route('store.index', ['category' => 'donation']))
        ->not->toContain(route('store.index', ['category' => 'publication']));

    $this->get('/store?category=publication')->assertRedirect(route('store.index'));

    $this->actingAs(userWithRole(RoleName::ContentEditor));
    Livewire::test(EditStoreItem::class, ['record' => StoreItem::query()->where('title', 'كتاب مسودة')->sole()->getRouteKey()])
        ->fillForm(['is_published' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($tabs())->toContain(route('store.index', ['category' => 'publication']));
    $this->get('/store?category=publication')->assertOk()->assertSee('كتاب مسودة')->assertDontSee('سهم تبرع');
});

it('draws the empty state with the all tab alone when nothing is published', function () {
    $html = (string) $this->get('/store')->assertOk()->assertSee('data-empty-state', false)->assertSee(__('store.page.empty'))->getContent();

    expect((string) str($html)->after('class="tabs-bar')->before('</nav>'))->toContain(__('store.page.tabs.all'))
        ->not->toContain('category=');
    $this->get('/store?category=donation')->assertRedirect(route('store.index'));
});

it('takes every card button from the catalog purchase link, ready for a Salla checkout later', function () {
    $this->app->instance(StoreCatalog::class, new FakeStoreCatalog(
        [new StoreProduct('سهم من الواجهة', 'وصف', 'https://ejaz.org.sa/p9', category: StoreItemCategory::Donation, sallaProductId: '9')],
        fn (StoreProduct $product, int $quantity): string => 'https://ejaz.org.sa/checkout/'.$product->sallaProductId.'?qty='.$quantity,
    ));

    $grid = (string) str($this->get('/store')->assertOk()->getContent())->after('data-store-grid')->before('</ul>');

    expect($grid)->toContain('سهم من الواجهة')
        ->toContain('href="https://ejaz.org.sa/checkout/9?qty=1"')
        ->not->toContain('href="https://ejaz.org.sa/p9"');
});

it('never shows an item whose stored link left the allowed store hosts', function () {
    StoreItem::factory()->create(['title' => 'رابط معدّل']);
    StoreItem::query()->update(['salla_url' => 'https://evil.example/p1']);

    $this->get('/store')->assertOk()->assertDontSee('رابط معدّل')->assertDontSee('evil.example');
    expect(app(ManualStoreCatalog::class)->products())->toBe([]);
});

it('shows a saved item at once, without waiting for the cached page to expire', function () {
    $this->get('/store')->assertOk()->assertSee(__('store.page.empty'));

    StoreItem::factory()->create(['title' => 'عنصر جديد']);

    $this->get('/store')->assertOk()->assertSee('عنصر جديد');
});

// ملاحظات المدير (4 نوفمبر 2026 ليلاً): كبسولة «المتجر الإلكتروني» تحت «تسجيل الدخول» بشكله في الصف الأول، وتحل محل زر الأيقونة
it('puts the store capsule under the login capsule in the header first row, one store link there, and leaves the seven menu items alone', function () {
    $html = (string) $this->get('/')->assertOk()->getContent();
    $top = (string) str($html)->after('class="header-top"')->before('data-main-nav');
    $pills = (string) str($top)->after('class="header-pills')->before('</div>');
    $store = preg_quote(route('store.index'), '#');

    expect($pills)->toMatch('#<a href="'.preg_quote(route('login'), '#').'" class="login-pill">[\s\S]*?</a>\s*<a href="'.$store.'"[^>]*wire:navigate[^>]*data-header-store#')
        ->toMatch('#data-header-store title="'.preg_quote(__('store.page.open'), '#').'"[^>]*class="login-pill[^"]*"#')
        ->toContain(__('store.menu.header'))
        ->and(substr_count($top, 'href="'.route('store.index').'"'))->toBe(1)
        ->and(__('store.menu.header'))->toBe('المتجر الإلكتروني');

    // صف القائمة سبعة عناصر بلا المتجر عنصراً رئيسياً (قسم في «الخدمات الإلكترونية» متى كان عنصرها في القائمة)
    preg_match_all('#<(?:a|button)[^>]*class="nav-capsule"#', (string) str($html)->after('data-main-nav')->before('</nav>'), $capsules);
    expect(implode('', $capsules[0]))->not->toContain(route('store.index'));
});

it('keeps the account capsule above the store capsule once signed in', function () {
    $this->actingAs(userWithRole(RoleName::Member));
    $pills = (string) str($this->get('/')->assertOk()->getContent())->after('class="header-pills')->before('</header>');

    // «حسابي» وبجانبه زر الخروج في .header-account، ثم كبسولة المتجر (decisions/2026-11-14-fix-header-logout-icon.md)
    expect($pills)->toMatch('#<div class="header-account">\s*<a href="'.preg_quote(route('account.dashboard'), '#').'"[^>]*class="login-pill">[\s\S]*?</a>\s*<form[^>]*data-header-logout>[\s\S]*?</form>\s*</div>\s*<a href="'.preg_quote(route('store.index'), '#').'"#');
});

it('keeps one store entry in the mobile menu, named like the header capsule', function () {
    $mobile = (string) str($this->get('/')->assertOk()->getContent())->after('id="mobile-menu"')->before('</header>');

    // باسم زر الرأس نفسه «المتجر الإلكتروني» (قرار المشرف على #179، البند 3)
    expect(substr_count($mobile, 'href="'.route('store.index').'"'))->toBe(1)
        ->and($mobile)->toMatch('#<a href="'.preg_quote(route('store.index'), '#').'"[^>]*data-menu-store[^>]*>\s*<span data-icon-tile[\s\S]*?</span>\s*'.preg_quote(__('store.menu.header'), '#').'\s*</a>#');
});

it('points the services store card and the footer store link to the store page, in the same tab', function () {
    $html = (string) $this->get('/services')->assertOk()->getContent();

    expect($html)->toContain('data-store-card')->toContain('data-store-link');

    $card = (string) str($html)->after('data-store-card')->before('</li>');
    $link = (string) str($html)->after('data-store-link')->before('</a>');
    $linkTag = (string) str($html)->before('data-store-link')->afterLast('<a ');

    // قرار المشرف على #179، البند 1 (أ): إلى /store لا إلى سلة مباشرة
    expect($card)->toContain('href="'.route('store.index').'"')->not->toContain('target="_blank"')
        ->and($linkTag)->toContain('href="'.route('store.index').'"')->not->toContain('target="_blank"')
        ->and($link)->not->toContain(__('site.opens_in_new_tab'));
});

it('refuses a malformed Salla product id in the action too, and keeps an empty one null', function (string $id) {
    $editor = userWithRole(RoleName::ContentEditor);

    expect(fn () => app(SaveStoreItem::class)->handle($editor, new StoreItem, ['title' => 'عنصر', 'category' => 'donation', 'salla_url' => 'https://ejaz.org.sa/p1', 'salla_product_id' => $id]))
        ->toThrow(ValidationException::class)
        ->and(StoreItem::query()->count())->toBe(0);

    $item = app(SaveStoreItem::class)->handle($editor, new StoreItem, ['title' => 'عنصر', 'category' => 'donation', 'salla_url' => 'https://ejaz.org.sa/p1', 'salla_product_id' => '  ']);
    expect($item->salla_product_id)->toBeNull();
})->with(['12 34', '<b>1</b>', 'ﻣﻨﺘﺞ', str_repeat('9', 51)]);

it('keeps the category to the enum values in the database too', function () {
    $item = StoreItem::factory()->create();

    // قيد CHECK بترحيل جديد (قرار المشرف على #179، البند 5)
    expect(fn () => DB::table('store_items')->where('id', $item->id)->update(['category' => 'other']))->toThrow(QueryException::class);
});

it('adds «متجر الجمعية» to the e-services dropdown, linking to the store page', function () {
    MenuItem::factory()->create(['label' => 'الخدمات الإلكترونية', 'url' => '/services', 'sort' => 1]);

    $dropdown = (string) str($this->get('/')->assertOk()->getContent())->after('data-main-nav')->before('</nav>');

    expect(MenuSections::for('/services'))->toContain(['label' => 'متجر الجمعية', 'url' => '/store'])
        ->and($dropdown)->toMatch('#<a href="'.preg_quote(url('/store'), '#').'"[^>]*wire:navigate[^>]*>متجر الجمعية</a>#');
});

it('lists /store in the sitemap', function () {
    $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>'.route('store.index').'</loc>', false);
});

it('opens the store screen to the content editor and the system managers, and keeps the board and the secretary out', function () {
    foreach ([RoleName::ContentEditor, RoleName::ExecutiveDirector, RoleName::TechnicalAdmin] as $role) {
        $this->actingAs(userWithRole($role))->get(StoreItemResource::getUrl('index'))->assertOk();
        $this->actingAs(userWithRole($role))->get(StoreItemResource::getUrl('create'))->assertOk();
    }

    $item = StoreItem::factory()->create();

    foreach ([RoleName::BoardMember, RoleName::Secretary, RoleName::Member] as $role) {
        $this->actingAs(userWithRole($role))->get(StoreItemResource::getUrl('index'))->assertForbidden();
        $this->actingAs(userWithRole($role))->get(StoreItemResource::getUrl('edit', ['record' => $item]))->assertForbidden();
    }
});

it('lets the read-only super admin look at the store screen without writing anything', function () {
    $item = StoreItem::factory()->create();
    $admin = userWithRole(RoleName::SuperAdmin);

    $this->actingAs($admin)->get(StoreItemResource::getUrl('index'))->assertOk();
    $this->actingAs($admin)->get(StoreItemResource::getUrl('create'))->assertForbidden();

    expect($admin->can('update', $item))->toBeFalse()
        ->and($admin->can('delete', $item))->toBeFalse()
        ->and(fn () => app(SaveStoreItem::class)->handle($admin, new StoreItem, ['title' => 'x', 'category' => 'donation', 'salla_url' => 'https://ejaz.org.sa/p1']))
        ->toThrow(AuthorizationException::class);
});

it('creates an item with its image and an optional Salla product id, logged', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(CreateStoreItem::class)
        ->fillForm([
            'title' => 'زكاتك حياة',
            'category' => StoreItemCategory::Donation->value,
            'description' => 'وصف قصير',
            'salla_url' => 'https://ejaz.org.sa/p1971442778',
            'salla_product_id' => '1971442778',
            'image' => [UploadedFile::fake()->image('item.jpg', 800, 600)],
            'is_published' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $item = StoreItem::query()->sole();

    expect($item->salla_product_id)->toBe('1971442778')
        ->and($item->image)->toStartWith(StoreItem::IMAGE_DIRECTORY.'/')
        ->and(Storage::disk('public')->exists($item->image))->toBeTrue()
        ->and(Activity::query()->whereMorphedTo('subject', $item)->where('event', 'created')->exists())->toBeTrue();

    $this->get('/store')->assertOk()->assertSee('زكاتك حياة')->assertSee(Storage::disk('public')->url($item->image));

    // معرّف سلة اختياري
    Livewire::test(CreateStoreItem::class)
        ->fillForm(['title' => 'إصدار', 'category' => 'publication', 'salla_url' => 'https://ejaz.org.sa/p2'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(StoreItem::query()->where('title', 'إصدار')->sole()->salla_product_id)->toBeNull();
});

it('logs an update and a delete, and keeps the deleted item out of the page', function () {
    $item = StoreItem::factory()->create(['title' => 'قبل التعديل']);
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(EditStoreItem::class, ['record' => $item->getRouteKey()])
        ->fillForm(['title' => 'بعد التعديل'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Activity::query()->whereMorphedTo('subject', $item)->where('event', 'updated')->exists())->toBeTrue();

    Livewire::test(ListStoreItems::class)->callAction(TestAction::make('delete')->table($item));

    expect(StoreItem::withTrashed()->find($item->id)?->trashed())->toBeTrue()
        ->and(Activity::query()->whereMorphedTo('subject', $item)->where('event', 'deleted')->exists())->toBeTrue();

    $this->get('/store')->assertOk()->assertDontSee('بعد التعديل');
});

it('refuses a link on another host or without https in the form', function (string $url) {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(CreateStoreItem::class)
        ->fillForm(['title' => 'عنصر', 'category' => 'donation', 'salla_url' => $url])
        ->call('create')
        ->assertHasFormErrors(['salla_url']);

    expect(StoreItem::query()->count())->toBe(0);
})->with(['https://evil.example/p1', 'http://ejaz.org.sa/p1', 'https://ejaz.org.sa.evil.example/p1', 'javascript:alert(1)', 'https://evil.com%2F.ejaz.org.sa']);

it('refuses a link on another host or without https in the action too, for every caller', function (?string $url) {
    $editor = userWithRole(RoleName::ContentEditor);

    expect(fn () => app(SaveStoreItem::class)->handle($editor, new StoreItem, ['title' => 'عنصر', 'category' => 'donation', 'salla_url' => $url]))
        ->toThrow(ValidationException::class);

    $item = StoreItem::factory()->create();

    expect(fn () => app(SaveStoreItem::class)->handle($editor, $item, ['salla_url' => $url]))->toThrow(ValidationException::class)
        ->and(StoreItem::query()->count())->toBe(1)
        ->and($item->fresh()->salla_url)->not->toBe($url);
})->with(['https://evil.example/p1', 'http://ejaz.org.sa/p1', 'javascript:alert(1)', null]);

it('refuses to publish an item still carrying a missing-text mark', function () {
    expect(fn () => app(SaveStoreItem::class)->handle(userWithRole(RoleName::ContentEditor), new StoreItem, [
        'title' => '【ناقص: اسم التبرع】', 'category' => 'donation', 'salla_url' => 'https://ejaz.org.sa/p1', 'is_published' => true,
    ]))->toThrow(ValidationException::class);
});

it('seeds seven labelled demo donations from the association store, and only locally', function () {
    $this->seed(DemoStoreItemsSeeder::class);

    $items = StoreItem::query()->published()->get();

    expect($items)->toHaveCount(7)
        ->and($items->every(fn (StoreItem $item): bool => str_contains($item->title, DemoStoreItemsSeeder::MARK) && $item->category === StoreItemCategory::Donation))->toBeTrue()
        ->and($items->pluck('salla_product_id')->filter())->toBeEmpty()
        ->and(app(ManualStoreCatalog::class)->products())->toHaveCount(7);

    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new DemoStoreItemsSeeder)->run())->toThrow(RuntimeException::class);
});
