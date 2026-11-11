<?php

use App\Actions\Content\PageEditor\EnsureEditablePage;
use App\Actions\Content\PageEditor\PublishPageDraft;
use App\Actions\Content\PageEditor\SavePageDraft;
use App\Enums\PartnerKind;
use App\Enums\RoleName;
use App\Filament\Resources\Partners\Pages\CreatePartner;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
| فجوتان في اللوحة ظهرتا عند تنفيذ «خطة الرفع» على الموقع الحي (11 نوفمبر 2026 — docs/decisions/2026-11-11-fix-panel-gaps-nov11.md):
| الشريك بلا شعار يُنشر باسمه، ونشر صفحة ثابتة بوصف فارغ لا يمحو وصف قالبها المعتمد.
*/

beforeEach(fn () => Storage::fake('public'));

/** رأس الصفحة وحده، لا وصفها في رأس المستند */
function panelGapsHero(string $html): string
{
    return (string) str($html)->after('data-page-hero')->before('</section>');
}

function panelGapsHeroImage(string $html): string
{
    preg_match('/<span class="page-hero-frame">\s*<img src="([^"]+)"/', $html, $match);

    return $match[1] ?? '';
}

/**
 * ينشر صفحة ثابتة من «محرر الصفحات» بوصف معطى وصورة رأس اختيارية، ويرد رابط الصورة أو null.
 */
function publishEditablePage(User $admin, string $key, string $title, ?string $summary, bool $withImage = false): ?string
{
    $page = app(EnsureEditablePage::class)->handle($admin, $key);
    $hero = null;

    if ($withImage) {
        $hero = 'settings/'.Str::random(20).'.png';
        Storage::disk('public')->put($hero, imageBytes('png', 40, 20));
    }

    app(SavePageDraft::class)->handle($admin, $page, [
        'title' => $title,
        'summary' => $summary,
        'image' => $hero,
        'blocks' => [['type' => 'text', 'data' => ['heading' => $title, 'body' => '<p>نص</p>', 'visible' => true]]],
    ]);
    app(PublishPageDraft::class)->handle($admin, $page);

    return $hero !== null ? Storage::disk('public')->url($hero) : null;
}

it('saves a published partner without a logo from the form, and shows it by name on the partners page and the home strip', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(CreatePartner::class)
        ->fillForm(['name' => 'جمعية ترياق', 'kind' => PartnerKind::Partner, 'is_published' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $partner = Partner::query()->where('name', 'جمعية ترياق')->sole();
    expect($partner->is_published)->toBeTrue()->and($partner->logo)->toBeNull();

    $this->get('/media/partners')->assertOk()->assertSee('جمعية ترياق')->assertSee('data-partner-no-logo', false);
    $this->get('/')->assertOk()->assertSee('<span class="partner-placeholder" data-partner-placeholder>جمعية ترياق</span>', false);
});

it('keeps the approved contact text when the contact page is published with an empty summary and a header image', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $image = publishEditablePage($admin, 'contact', __('contact.title'), null, withImage: true);

    $html = (string) $this->get('/contact')->assertOk()->getContent();
    [$first, $second] = preg_split('/\n\s*\n/', trim(__('contact.hero_text')));

    $hero = panelGapsHero($html);

    expect(substr_count($hero, 'data-hero-summary'))->toBe(2)
        ->and($hero)->toContain(e($first))
        ->and($hero)->toContain(e($second))
        ->and(panelGapsHeroImage($html))->toBe($image);
});

it('still lets the editor clear the services summary, whose original text is in the editor', function () {
    publishEditablePage(userWithRole(RoleName::TechnicalAdmin), 'services', __('services.title'), null);

    $hero = panelGapsHero((string) $this->get('/services')->assertOk()->getContent());

    expect($hero)->not->toContain('data-hero-summary')
        ->and($hero)->not->toContain(e(__('services.intro')));
});

it('lets a written editor summary win on the contact page', function () {
    publishEditablePage(userWithRole(RoleName::TechnicalAdmin), 'contact', __('contact.title'), 'وصف مكتوب من المحرر');

    $hero = panelGapsHero((string) $this->get('/contact')->assertOk()->getContent());

    expect($hero)->toContain('وصف مكتوب من المحرر')
        ->and($hero)->not->toContain(e(Str::before(__('contact.hero_text'), '،')));
});
