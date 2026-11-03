<?php

use App\Enums\RoleName;
use App\Filament\Resources\Competitions\CompetitionResource;
use App\Filament\Resources\Competitions\Pages\ViewCompetition;
use App\Filament\Resources\Meetings\MeetingResource;
use App\Filament\Resources\Meetings\Pages\ViewMeeting;
use App\Models\Competition;
use App\Models\Meeting;
use Livewire\Livewire;

/**
 * فحص بوابة عضو المجلس قبل 0.15.0 (docs/reviews/2026-11-03-v0.15-board-portal-qa.md): تبويبات صفحة المسابقة والاجتماع
 * التي يراها عضو المجلس اسم سجلها عربي، فلا تظهر حالتها الفارغة «لا يوجد competition draws» ولا أزرارها بالإنجليزية.
 * Filament يشتق اسم السجل من اسم الصنف إن لم يُعطَ، فيُفحص كل تبويب ظاهر له.
 */
dataset('board record pages', [
    'standard competition' => [fn () => Competition::factory()->closed()->create(), CompetitionResource::class, ViewCompetition::class],
    'student competition' => [fn () => Competition::factory()->student()->create(), CompetitionResource::class, ViewCompetition::class],
    'meeting' => [fn () => Meeting::factory()->create(), MeetingResource::class, ViewMeeting::class],
]);

it('names the records of every tab a board member sees in Arabic', function (Closure $record, string $resource, string $page) {
    $record = $record();
    $this->actingAs(userWithRole(RoleName::BoardMember));
    $checked = 0;
    $english = [];

    foreach ($resource::getRelations() as $manager) {
        if (! $manager::canViewForRecord($record, $page)) {
            continue;
        }

        $table = Livewire::test($manager, ['ownerRecord' => $record, 'pageClass' => $page])->instance()->getTable();

        foreach ([$table->getModelLabel(), $table->getPluralModelLabel()] as $label) {
            if (preg_match('/[A-Za-z]/', $label)) {
                $english[] = class_basename($manager).': '.$label;
            }
        }
        $checked++;
    }

    expect($checked)->toBeGreaterThan(0)
        ->and($english)->toBe([]);
})->with('board record pages');
