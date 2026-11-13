<?php

use App\Enums\RoleName;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use Livewire\Livewire;

/*
| زر التصفية في جداول اللوحة أيقونة بشارة عدد التصفيات المفعّلة ولو صفراً، فاسمه يضم العدد الظاهر (axe: label-content-name-mismatch في
| بنك الأسئلة وجداول اللوحة — docs/decisions/2026-11-11-fix-post-launch-polish.md).
*/

it('names the table filter button with the active filter count it shows', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(ListDocuments::class)
        ->assertSee(__('admin.filters_trigger', ['count' => 0]))
        ->filterTable('category', 'policies')
        ->assertSee(__('admin.filters_trigger', ['count' => 1]));
});

it('shows «تصفية» alone as the hover tooltip with no active filter, and the count once one is on', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    $component = Livewire::test(ListDocuments::class);
    expect($component->instance()->getTable()->getFiltersTriggerAction()->getTooltip())->toBe(__('admin.filters_tooltip'));

    $component->filterTable('category', 'policies');
    expect($component->instance()->getTable()->getFiltersTriggerAction()->getTooltip())->toBe(__('admin.filters_trigger', ['count' => 1]));
});
