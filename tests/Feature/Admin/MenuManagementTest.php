<?php

use App\Enums\RoleName;
use App\Filament\Resources\MenuItems\MenuItemResource;
use App\Filament\Resources\MenuItems\Pages\CreateMenuItem;
use App\Filament\Resources\MenuItems\Pages\EditMenuItem;
use App\Filament\Resources\MenuItems\Pages\ListMenuItems;
use App\Models\MenuItem;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->editor = userWithRole(RoleName::ContentEditor));

it('shows the menu screen only to those allowed', function () {
    $this->actingAs($this->editor)->get(MenuItemResource::getUrl('index'))->assertOk();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(MenuItemResource::getUrl('index'))->assertOk();
    $this->actingAs(userWithRole(RoleName::Secretary))->get(MenuItemResource::getUrl('index'))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Reviewer))->get(MenuItemResource::getUrl('create'))->assertForbidden();
});

it('lets board members look but not change', function () {
    $item = MenuItem::factory()->create();

    $this->actingAs(userWithRole(RoleName::BoardMember))
        ->get(MenuItemResource::getUrl('edit', ['record' => $item]))
        ->assertForbidden();
});

it('creates a menu item, logs it and shows it on the site at once', function () {
    $this->get('/')->assertDontSee('المكتبة الجديدة');

    $this->actingAs($this->editor);

    Livewire::test(CreateMenuItem::class)
        ->fillForm(['label' => 'المكتبة الجديدة', 'url' => '/library', 'sort' => 3, 'is_visible' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(MenuItem::where('label', 'المكتبة الجديدة')->exists())->toBeTrue()
        ->and(Activity::where('subject_type', MenuItem::class)->where('event', 'created')->exists())->toBeTrue();

    $this->get('/')->assertSee('المكتبة الجديدة');
});

it('rejects unsafe links', function (string $url) {
    $this->actingAs($this->editor);

    Livewire::test(CreateMenuItem::class)
        ->fillForm(['label' => 'رابط', 'url' => $url])
        ->call('create')
        ->assertHasFormErrors(['url']);
})->with(['javascript:alert(1)', 'data:text/html,x', '//evil.com', '/\evil.com', '/%5Cevil.com']);

it('keeps the menu at two levels', function () {
    $parent = MenuItem::factory()->create();
    $child = MenuItem::factory()->create(['parent_id' => $parent->id]);
    $other = MenuItem::factory()->create();

    $this->actingAs($this->editor);

    Livewire::test(EditMenuItem::class, ['record' => $parent->getRouteKey()])
        ->fillForm(['parent_id' => $other->id])
        ->call('save')
        ->assertHasFormErrors(['parent_id']);

    expect($child->fresh()->parent_id)->toBe($parent->id);
});

it('refreshes the site menu after reordering', function () {
    $first = MenuItem::factory()->create(['label' => 'الأول', 'sort' => 1]);
    $second = MenuItem::factory()->create(['label' => 'الثاني', 'sort' => 2]);

    $this->get('/')->assertSeeInOrder(['الأول', 'الثاني']);

    $this->actingAs($this->editor);

    Livewire::test(ListMenuItems::class)->call('reorderTable', [$second->getKey(), $first->getKey()]);

    $this->get('/')->assertSeeInOrder(['الثاني', 'الأول']);

    $log = Activity::where('event', 'menu_reordered')->firstOrFail();
    expect($log->properties['old'])->toBe([$first->id, $second->id])
        ->and($log->properties['new'])->toBe([$second->id, $first->id]);
});

it('does not let board members reorder the menu, even by calling the table directly', function () {
    $first = MenuItem::factory()->create(['sort' => 1]);
    $second = MenuItem::factory()->create(['sort' => 2]);

    $this->actingAs(userWithRole(RoleName::BoardMember));

    Livewire::test(ListMenuItems::class)->call('reorderTable', [$second->getKey(), $first->getKey()]);

    expect($first->fresh()->sort)->toBe(1)->and($second->fresh()->sort)->toBe(2);
});

it('refuses in the database too to delete an item that has sub-items', function () {
    $parent = MenuItem::factory()->create();
    MenuItem::factory()->create(['parent_id' => $parent->id]);

    expect(fn () => DB::transaction(fn () => $parent->delete()))->toThrow(QueryException::class)
        ->and(MenuItem::query()->count())->toBe(2);
});

it('refuses to delete an item that has sub-items', function () {
    $parent = MenuItem::factory()->create();
    $child = MenuItem::factory()->create(['parent_id' => $parent->id]);

    expect($this->editor->can('delete', $parent))->toBeFalse()
        ->and(userWithRole(RoleName::TechnicalAdmin)->can('delete', $parent))->toBeFalse()
        ->and($this->editor->can('delete', $child))->toBeTrue();

    $this->actingAs($this->editor);

    Livewire::test(ListMenuItems::class)
        ->assertActionHidden(TestAction::make('delete')->table($parent))
        ->callAction(TestAction::make('delete')->table($child));

    expect(MenuItem::find($child->id))->toBeNull()->and(MenuItem::find($parent->id))->not->toBeNull();
});
