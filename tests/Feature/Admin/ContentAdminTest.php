<?php

use App\Actions\Content\DeleteContent;
use App\Actions\Content\SaveContent;
use App\Enums\RoleName;
use App\Filament\Resources\BoardMembers\BoardMemberResource;
use App\Filament\Resources\BoardMembers\Pages\CreateBoardMember;
use App\Filament\Resources\ContentPages\ContentPageResource;
use App\Filament\Resources\ContentPages\Pages\CreateContentPage;
use App\Filament\Resources\ContentPages\Pages\EditContentPage;
use App\Filament\Resources\Initiatives\InitiativeResource;
use App\Filament\Resources\Initiatives\Pages\CreateInitiative;
use App\Filament\Resources\Initiatives\Pages\EditInitiative;
use App\Filament\Resources\Initiatives\Pages\ListInitiatives;
use App\Models\BoardMember;
use App\Models\Concerns\FlushesContentCache;
use App\Models\Contracts\HasPublicImages;
use App\Models\Initiative;
use App\Models\Page;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('public');
    $this->editor = userWithRole(RoleName::ContentEditor);
});

/**
 * صورة JPEG فيها بيانات موقع (EXIF) تُكتشف بعد التنظيف.
 */
function photoWithLocation(string $name = 'photo.jpg', int $width = 800, int $height = 500): UploadedFile
{
    $jpeg = UploadedFile::fake()->image($name, $width, $height)->getContent();
    $payload = "Exif\0\0GPS-LATITUDE-21.4225";

    return UploadedFile::fake()->createWithContent($name, substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2));
}

it('opens the content screens to the editor, lets the board look only, and keeps members out', function () {
    $resources = [ContentPageResource::class, InitiativeResource::class, BoardMemberResource::class];

    foreach ($resources as $resource) {
        $this->actingAs($this->editor)->get($resource::getUrl('index'))->assertOk();
        $this->actingAs($this->editor)->get($resource::getUrl('create'))->assertOk();
        $this->actingAs(userWithRole(RoleName::BoardMember))->get($resource::getUrl('index'))->assertOk();
        $this->actingAs(userWithRole(RoleName::BoardMember))->get($resource::getUrl('create'))->assertForbidden();
        $this->actingAs(userWithRole(RoleName::Secretary))->get($resource::getUrl('index'))->assertForbidden();
    }
});

it('refuses to save or delete content for someone without the permission, wherever it is called from', function () {
    $board = userWithRole(RoleName::BoardMember);
    $initiative = Initiative::factory()->create();

    expect(fn () => app(SaveContent::class)->handle($board, new Initiative, ['slug' => 'x', 'name' => 'x']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveContent::class)->handle($board, $initiative, ['name' => 'تغيير']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeleteContent::class)->handle($board, $initiative))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveContent::class)->handle(User::factory()->create(), new Page, ['slug' => 'x', 'title' => 'x']))->toThrow(AuthorizationException::class);

    expect($initiative->fresh()->name)->not->toBe('تغيير');
});

it('creates an initiative with a cleaned image, logs it and shows it on the site at once', function () {
    $this->actingAs($this->editor);

    Livewire::test(CreateInitiative::class)
        ->fillForm([
            'name' => 'قيم',
            'slug' => 'qiyam',
            'summary' => 'ملخص المبادرة',
            'image' => [photoWithLocation()],
            // المكرر البسيط يحفظ الأهداف قائمة نصوص، وحالته في النموذج عنصر لكل هدف
            'goals' => [['goal' => 'الهدف الأول']],
            'is_published' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $initiative = Initiative::where('slug', 'qiyam')->sole();

    expect(Storage::disk('public')->get($initiative->image))->not->toContain('GPS-LATITUDE')
        ->and($initiative->image)->toStartWith('initiatives/')
        ->and($initiative->goals)->toBe(['الهدف الأول'])
        ->and(Activity::whereMorphedTo('subject', $initiative)->where('event', 'created')->exists())->toBeTrue();

    $this->get('/initiatives/qiyam')->assertOk()->assertSee('ملخص المبادرة');
});

it('accepts only lowercase latin slugs, once each', function (string $slug) {
    Initiative::factory()->create(['slug' => 'taken']);
    $this->actingAs($this->editor);

    Livewire::test(CreateInitiative::class)
        ->fillForm(['name' => 'مبادرة', 'slug' => $slug])
        ->call('create')
        ->assertHasFormErrors(['slug']);
})->with(['Qiyam', 'قيم', 'two words', 'a--b', '-start', 'taken', '../etc']);

it('keeps the images of the blocks when an initiative is saved again', function () {
    $initiative = Initiative::factory()->create(['slug' => 'qiyam']);
    $this->actingAs($this->editor);

    Livewire::test(EditInitiative::class, ['record' => $initiative->getRouteKey()])
        ->fillForm(['blocks' => [['type' => 'image', 'data' => ['image' => [photoWithLocation('block.jpg')], 'caption' => 'وصف']]]])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = $initiative->fresh()->blocks[array_key_first($initiative->fresh()->blocks)]['data']['image'];
    expect(Storage::disk('public')->get($path))->not->toContain('GPS-LATITUDE');

    // حفظ ثانٍ بلا تغيير في الصورة: مسارها المحفوظ مسموح، لا «مسار غير مسموح به»
    Livewire::test(EditInitiative::class, ['record' => $initiative->getRouteKey()])
        ->fillForm(['name' => 'اسم معدّل'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($initiative->fresh()->name)->toBe('اسم معدّل')
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});

it('removes the replaced image, and every image of a deleted initiative', function () {
    Storage::disk('public')->put('initiatives/old.jpg', UploadedFile::fake()->image('old.jpg')->getContent());
    Storage::disk('public')->put('initiatives/gallery.jpg', UploadedFile::fake()->image('gallery.jpg')->getContent());
    $initiative = Initiative::factory()->create(['image' => 'initiatives/old.jpg', 'gallery' => ['initiatives/gallery.jpg']]);

    app(SaveContent::class)->handle($this->editor, $initiative, ['image' => null]);
    expect(Storage::disk('public')->exists('initiatives/old.jpg'))->toBeFalse()
        ->and(Storage::disk('public')->exists('initiatives/gallery.jpg'))->toBeTrue();

    $this->actingAs($this->editor);
    Livewire::test(ListInitiatives::class)->callAction(TestAction::make('delete')->table($initiative));

    expect(Initiative::find($initiative->id))->toBeNull()
        ->and(Storage::disk('public')->exists('initiatives/gallery.jpg'))->toBeFalse();
});

it('rejects an image that cannot be read, under its own field', function () {
    $this->actingAs($this->editor);
    $header = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NN', 800, 400)."\x08\x02\x00\x00\x00";
    $broken = UploadedFile::fake()->createWithContent('broken.png', $header.pack('N', crc32(substr($header, 12))).random_bytes(2048));

    $component = Livewire::test(CreateInitiative::class)
        ->fillForm(['name' => 'مبادرة', 'slug' => 'broken', 'image' => [$broken]])
        ->call('create');

    expect($component->errors()->first('data.image'))->toBe(__('validation.unreadable_image'))
        ->and(Initiative::where('slug', 'broken')->exists())->toBeFalse()
        ->and(Storage::disk('public')->allFiles('initiatives'))->toBe([]);
});

it('never deletes the about page nor changes its link, even for the super admin', function () {
    $about = Page::factory()->create(['slug' => Page::ABOUT]);
    $admin = userWithRole(RoleName::SuperAdmin);

    expect($admin->can('delete', $about))->toBeFalse()
        ->and(fn () => app(DeleteContent::class)->handle($admin, $about))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveContent::class)->handle($admin, $about, ['slug' => 'about-us']))->toThrow(ValidationException::class);

    $this->actingAs($this->editor);
    Livewire::test(EditContentPage::class, ['record' => $about->getRouteKey()])
        ->assertFormFieldIsDisabled('slug')
        ->assertActionHidden('delete');

    expect($about->fresh()->slug)->toBe(Page::ABOUT);
});

it('records the order of the initiatives and shows it on the site at once', function () {
    $first = Initiative::factory()->create(['name' => 'الأولى', 'sort' => 1]);
    $second = Initiative::factory()->create(['name' => 'الثانية', 'sort' => 2]);
    $this->get('/initiatives')->assertSeeInOrder(['الأولى', 'الثانية']);

    $this->actingAs($this->editor);
    Livewire::test(ListInitiatives::class)->call('reorderTable', [(string) $second->id, (string) $first->id]);

    $this->get('/initiatives')->assertSeeInOrder(['الثانية', 'الأولى']);
    expect(Activity::where('event', 'content_reordered')->exists())->toBeTrue();
});

it('does not let the board reorder the initiatives, even by calling the table directly', function () {
    $first = Initiative::factory()->create(['sort' => 1]);
    $second = Initiative::factory()->create(['sort' => 2]);

    $this->actingAs(userWithRole(RoleName::BoardMember));
    Livewire::test(ListInitiatives::class)->call('reorderTable', [(string) $second->id, (string) $first->id]);

    expect($first->fresh()->sort)->toBe(1)->and($second->fresh()->sort)->toBe(2);
});

it('puts a new initiative and board member at the end of the order', function () {
    Initiative::factory()->create(['sort' => 7]);
    BoardMember::factory()->create(['sort' => 3]);

    $initiative = app(SaveContent::class)->handle($this->editor, new Initiative, ['slug' => 'new', 'name' => 'جديدة']);
    $member = app(SaveContent::class)->handle($this->editor, new BoardMember, ['name' => 'عضو', 'position' => 'صفة']);

    expect($initiative->sort)->toBe(8)->and($member->sort)->toBe(4);
});

it('flushes the public content cache from every model that has public images', function () {
    $models = collect(File::files(app_path('Models')))
        ->map(fn ($file) => 'App\\Models\\'.$file->getFilenameWithoutExtension())
        ->filter(fn (string $class) => is_subclass_of($class, HasPublicImages::class));

    expect($models)->not->toBeEmpty();

    foreach ($models as $class) {
        expect(class_uses_recursive($class))->toHaveKey(FlushesContentCache::class);
    }
});

it('stores the rich text written in the editor and shows it on the site', function () {
    $page = Page::factory()->create(['slug' => Page::ABOUT, 'title' => 'عن الجمعية']);
    $this->actingAs($this->editor);

    Livewire::test(EditContentPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['blocks' => [['type' => 'text', 'data' => ['heading' => 'من نحن', 'body' => '<p>نص <strong>من المحرر</strong></p>']]]])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get('/about')->assertSee('<strong>من المحرر</strong>', false);
});

it('creates a page and a board member from the panel', function () {
    $this->actingAs($this->editor);

    Livewire::test(CreateContentPage::class)
        ->fillForm(['title' => 'سياسة الخصوصية', 'slug' => 'privacy', 'is_published' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateBoardMember::class)
        ->fillForm(['name' => 'عضو', 'position' => 'صفة', 'photo' => [photoWithLocation('member.jpg', 500, 500)], 'is_visible' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $member = BoardMember::where('name', 'عضو')->sole();

    expect(Page::where('slug', 'privacy')->exists())->toBeTrue()
        ->and($member->photo)->toStartWith('board/')
        ->and(Storage::disk('public')->get($member->photo))->not->toContain('GPS-LATITUDE');
});

it('refuses an image path outside the folder of the record, and deletes nothing there', function (string $path) {
    Storage::disk('public')->put('settings/hero.jpg', UploadedFile::fake()->image('hero.jpg')->getContent());
    $initiative = Initiative::factory()->create();

    expect(fn () => app(SaveContent::class)->handle($this->editor, $initiative, ['image' => $path]))
        ->toThrow(ValidationException::class)
        ->and(Storage::disk('public')->exists('settings/hero.jpg'))->toBeTrue()
        ->and($initiative->fresh()->image)->toBeNull();
})->with(['settings/hero.jpg', 'initiatives/../settings/hero.jpg']);

it('lets the editor save again after a rejected image, keeping the stored one', function () {
    Storage::disk('public')->put('initiatives/current.jpg', UploadedFile::fake()->image('current.jpg', 800, 500)->getContent());
    $initiative = Initiative::factory()->create(['image' => 'initiatives/current.jpg']);
    $header = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NN', 800, 400)."\x08\x02\x00\x00\x00";
    $broken = UploadedFile::fake()->createWithContent('broken.png', $header.pack('N', crc32(substr($header, 12))).random_bytes(2048));

    $this->actingAs($this->editor);

    $component = Livewire::test(EditInitiative::class, ['record' => $initiative->getRouteKey()])
        ->set('data.image', null)
        ->fillForm(['image' => [$broken]])
        ->call('save');

    expect($component->errors()->first('data.image'))->toBe(__('validation.unreadable_image'));

    $component->fillForm(['name' => 'اسم بعد الرفض'])->call('save')->assertHasNoFormErrors();

    expect($initiative->fresh())->name->toBe('اسم بعد الرفض')->image->toBe('initiatives/current.jpg');
});
