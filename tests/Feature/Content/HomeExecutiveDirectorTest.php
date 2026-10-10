<?php

use App\Enums\LibrarySection;
use App\Enums\PartnerKind;
use App\Enums\RoleName;
use App\Filament\Resources\LibraryItems\Pages\EditLibraryItem;
use App\Filament\Resources\Partners\Pages\EditPartner;
use App\Filament\Resources\Partners\PartnerResource;
use App\Models\LibraryItem;
use App\Models\Partner;
use App\Models\ProgramType;
use App\Models\Setting;
use Database\Seeders\ProgramTypesSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * ملاحظات المدير التنفيذي على الرئيسية ومحور «الدورات والبرامج».
 * docs/requirements/12-executive-director-2026-10-10.md (القسمان 3 و4)
 */
it('gives the competitions their own shortcut and names the programs shortcut courses and programs', function () {
    $this->get('/')->assertOk()
        ->assertSee(__('home.shortcuts.competitions.title'))
        ->assertSee('href="'.route('competitions.index').'"', false)
        ->assertSee(__('home.shortcuts.services.title'));

    expect(__('home.shortcuts.services.title'))->toBe('الدورات والبرامج');
});

it('shows up to three chosen published YouTube videos, playing only on request', function () {
    $chosen = LibraryItem::factory()->video('https://www.youtube.com/watch?v=aaaaaaaaaaa')->count(4)
        ->sequence(fn ($sequence) => ['title' => 'مرئي مختار '.$sequence->index, 'published_on' => now()->subDays($sequence->index)])
        ->create(['on_home' => true]);
    LibraryItem::factory()->video()->create(['title' => 'مرئي غير مختار']);
    LibraryItem::factory()->video()->unpublished()->create(['title' => 'مرئي مسودة', 'on_home' => true]);
    LibraryItem::factory()->create(['title' => 'كتاب مختار', 'section' => LibrarySection::Books, 'on_home' => true]);

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->toContain(__('home.videos.title'))
        ->toContain('مرئي مختار 0')->toContain('مرئي مختار 2')
        ->not->toContain('مرئي مختار 3')
        ->not->toContain('مرئي غير مختار')
        ->not->toContain('مرئي مسودة')
        ->not->toContain('كتاب مختار')
        ->toContain('data-video-embed="https://www.youtube-nocookie.com/embed/aaaaaaaaaaa?autoplay=1"')
        ->not->toContain('<iframe')
        ->toContain(route('library.section', LibrarySection::Videos->value));

    expect($chosen)->toHaveCount(4);
});

it('shows the official accounts from the site settings, and no section without them', function () {
    $this->get('/')->assertDontSee(__('home.accounts.title'));

    Setting::put('contact', ['social' => ['x' => 'https://x.com/example', 'youtube' => 'https://www.youtube.com/@example']]);

    $this->get('/')->assertOk()
        ->assertSee(__('home.accounts.title'))
        ->assertSee(__('settings.social.x'))
        ->assertSee(__('settings.social.youtube'))
        ->assertSee('href="https://x.com/example"', false)
        ->assertSee('rel="noopener noreferrer"', false);
});

it('groups partners and sponsors, and keeps one group without sponsors', function () {
    Partner::factory()->create(['name' => 'شريك تجريبي']);

    $this->get('/')->assertOk()
        ->assertSee(__('home.partners.title'))
        ->assertDontSee('>'.__('home.partners.sponsors').'</h3>', false);

    Partner::factory()->create(['name' => 'راعٍ تجريبي', 'kind' => PartnerKind::Sponsor]);

    $this->get('/')->assertOk()
        ->assertSeeInOrder([__('home.partners.partners'), 'شريك تجريبي', __('home.partners.sponsors'), 'راعٍ تجريبي']);
});

it('hides the videos and accounts sections when switched off', function () {
    LibraryItem::factory()->video()->create(['on_home' => true]);
    Setting::put('contact', ['social' => ['x' => 'https://x.com/example']]);
    Setting::put('home', ['show_videos' => false, 'show_accounts' => false]);

    $this->get('/')->assertOk()
        ->assertDontSee(__('home.videos.title'))
        ->assertDontSee(__('home.accounts.title'));
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
    $this->actingAs(userWithRole(RoleName::BoardMember))
        ->get(PartnerResource::getUrl('edit', ['record' => $partner]))->assertForbidden();
});
