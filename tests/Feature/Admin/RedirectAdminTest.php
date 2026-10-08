<?php

use App\Actions\Content\SaveRedirect;
use App\Enums\RedirectType;
use App\Enums\RoleName;
use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Filament\Resources\Redirects\RedirectResource;
use App\Models\Redirect;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->editor = userWithRole(RoleName::ContentEditor));

it('opens the redirects screen to the editor only', function () {
    $this->actingAs($this->editor)->get(RedirectResource::getUrl('index'))->assertOk();
    $this->actingAs($this->editor)->get(RedirectResource::getUrl('create'))->assertOk();

    foreach ([RoleName::BoardMember, RoleName::Secretary, RoleName::Reviewer] as $role) {
        $this->actingAs(userWithRole($role))->get(RedirectResource::getUrl('index'))->assertForbidden();
    }
});

it('keeps the edit page and the delete action from those not allowed', function () {
    $redirect = Redirect::factory()->create();
    $this->actingAs(userWithRole(RoleName::BoardMember));

    $this->get(RedirectResource::getUrl('edit', ['record' => $redirect]))->assertForbidden();
    expect(Redirect::query()->whereKey($redirect->getKey())->exists())->toBeTrue();
});

it('creates a redirect from an old link copied from the browser, logs it and follows it at once', function () {
    $this->actingAs($this->editor);

    Livewire::test(CreateRedirect::class)
        ->fillForm([
            'source' => 'https://ejaz.sa/'.rawurlencode('صفحة-قديمة').'/?ref=old',
            'target' => '/about',
            'type' => RedirectType::Permanent->value,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $redirect = Redirect::query()->sole();

    expect($redirect->source)->toBe('صفحة-قديمة')
        ->and(Activity::whereMorphedTo('subject', $redirect)->where('event', 'created')->exists())->toBeTrue();

    $this->get('/'.rawurlencode('صفحة-قديمة'))->assertStatus(301)->assertRedirect('/about');
});

it('refuses a redirect that would not work, leave the portal or go round in circles', function (array $form, string $field, string $error) {
    Redirect::factory()->create(['source' => 'old-a', 'target' => '/contact']);
    Redirect::factory()->create(['source' => 'old-b', 'target' => '/old-c']);
    $this->actingAs($this->editor);

    Livewire::test(CreateRedirect::class)
        ->fillForm([...['source' => '/old-new', 'target' => '/contact', 'type' => RedirectType::Permanent->value], ...$form])
        ->call('create')
        ->assertHasFormErrors([$field => __($error)]);

    expect(Redirect::count())->toBe(2);
})->with([
    'another site' => [['target' => 'https://evil.example/page'], 'target', 'redirects.errors.target'],
    'a protocol-relative link' => [['target' => '//evil.example'], 'target', 'redirects.errors.target'],
    'a backslash' => [['target' => '/\\evil.example'], 'target', 'redirects.errors.target'],
    'a script' => [['target' => 'javascript:alert(1)'], 'target', 'redirects.errors.target'],
    'a space' => [['target' => '/a page'], 'target', 'redirects.errors.target'],
    'Arabic letters' => [['target' => '/التطوع'], 'target', 'redirects.errors.target'],
    'the home page' => [['source' => '/'], 'source', 'redirects.errors.home'],
    'a page the portal serves' => [['source' => '/about'], 'source', 'redirects.errors.served'],
    'a path of a portal route' => [['source' => '/media/news/some-article'], 'source', 'redirects.errors.served'],
    'a duplicate' => [['source' => '/old-a/'], 'source', 'redirects.errors.duplicate'],
    'itself' => [['source' => '/old-new', 'target' => '/old-new?x=1'], 'target', 'redirects.errors.loop'],
    'a target with its own redirect' => [['target' => '/old-a'], 'target', 'redirects.errors.chain_target'],
    'a source that is a target' => [['source' => '/old-c'], 'source', 'redirects.errors.chain_source'],
]);

it('edits and deletes a redirect, and the site follows the change at once', function () {
    $redirect = Redirect::factory()->create(['source' => 'old-a', 'target' => '/contact']);
    $this->actingAs($this->editor);

    Livewire::test(EditRedirect::class, ['record' => $redirect->getRouteKey()])
        ->fillForm(['target' => '/library', 'type' => RedirectType::Temporary->value])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get('/old-a')->assertStatus(302)->assertRedirect('/library');

    Livewire::test(ListRedirects::class)->callAction(TestAction::make('delete')->table($redirect));

    expect(Redirect::count())->toBe(0)
        ->and(Activity::query()->where('subject_type', $redirect->getMorphClass())->where('event', 'deleted')->exists())->toBeTrue();
    $this->get('/old-a')->assertNotFound();
});

it('checks the permission in the action itself, whoever calls it', function () {
    $redirect = Redirect::factory()->create();

    expect(fn () => app(SaveRedirect::class)->handle(userWithRole(RoleName::BoardMember), new Redirect, ['source' => '/x', 'target' => '/about', 'type' => 'permanent']))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveRedirect::class)->handle(userWithRole(RoleName::Secretary), $redirect, ['source' => $redirect->source, 'target' => '/about', 'type' => 'permanent']))
        ->toThrow(AuthorizationException::class);
});
