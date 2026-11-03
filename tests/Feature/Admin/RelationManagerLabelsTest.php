<?php

use App\Enums\RoleName;
use App\Filament\Resources\Competitions\CompetitionResource;
use App\Filament\Resources\Competitions\Pages\ViewCompetition;
use App\Filament\Resources\EvidenceGalleries\EvidenceGalleryResource;
use App\Filament\Resources\EvidenceGalleries\Pages\EditEvidenceGallery;
use App\Filament\Resources\SitePages\Pages\EditSitePage;
use App\Filament\Resources\SitePages\SitePageResource;
use App\Models\Competition;
use App\Models\EvidenceGallery;
use App\Models\Page;
use Livewire\Livewire;

/**
 * تتمة فحص بوابة المجلس (docs/reviews/2026-11-03-v0.15-board-portal-qa.md): Filament يشتق اسم السجل من اسم الصنف
 * إن لم يُعطَ، فتظهر الحالة الفارغة «لا يوجد competition submissions». فكل تبويب في اللوحة يُعطى اسماً عربياً.
 */
it('gives every relation manager in the panel an explicit record name', function () {
    $missing = collect(glob(app_path('Filament/Resources/*/RelationManagers/*.php')) ?: [])
        ->reject(fn (string $file): bool => str_contains((string) file_get_contents($file), '->modelLabel('))
        ->map(fn (string $file): string => basename($file, '.php'))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

dataset('administrator record pages', [
    'student competition' => [fn () => Competition::factory()->student()->create(), CompetitionResource::class, ViewCompetition::class],
    'standard competition' => [fn () => Competition::factory()->closed()->create(), CompetitionResource::class, ViewCompetition::class],
    'evidence gallery' => [fn () => EvidenceGallery::factory()->create(), EvidenceGalleryResource::class, EditEvidenceGallery::class],
    'site page' => [fn () => Page::factory()->create(), SitePageResource::class, EditSitePage::class],
]);

// النص المعروض لمديري النظام: الحالة الفارغة لكل تبويب يرونه باسم عربي، ولا اسم لاتيني بعد «لا يوجد»
it('shows every tab an administrator sees with an Arabic empty state', function (Closure $record, string $resource, string $page) {
    $record = $record();
    $this->actingAs(userWithRole(RoleName::ExecutiveDirector));
    $checked = [];

    foreach ($resource::getRelations() as $manager) {
        if (! $manager::canViewForRecord($record, $page)) {
            continue;
        }

        $component = Livewire::test($manager, ['ownerRecord' => $record, 'pageClass' => $page]);
        $text = preg_replace('/\s+/u', ' ', strip_tags($component->html()));
        $plural = $component->instance()->getTable()->getPluralModelLabel();

        expect($text)->not->toMatch('/لا يوجد\s+[A-Za-z]/u', class_basename($manager))
            ->and($plural)->not->toMatch('/[A-Za-z]/', class_basename($manager));

        // التبويب الفارغ يعرض حالته الفارغة بالاسم العربي (المراحل في مسابقة الطلاب تبدأ بمرحلة من المصنع)
        if ($component->instance()->getTableRecords()->isEmpty()) {
            expect($text)->toContain(__('filament-tables::table.empty.heading', ['model' => $plural]));
        }
        $checked[] = class_basename($manager);
    }

    expect($checked)->not->toBeEmpty();
})->with('administrator record pages');
