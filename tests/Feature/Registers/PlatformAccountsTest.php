<?php

use App\Actions\Registers\SavePlatformAccount;
use App\Enums\RoleName;
use App\Models\PlatformAccount;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/*
| حسابات المنصات بلا كلمات مرور (requirements/13 §4، والقرار في decisions.md): لا خانة لكلمة مرور في أي
| جدول من جداول هذه الوحدة.
*/

beforeEach(function () {
    $this->director = userWithRole(RoleName::ExecutiveDirector);
});

function platformData(array $overrides = []): array
{
    return ['platform' => 'منصة تجريبية', 'responsible_id' => null, 'username' => 'demo-user', 'login_url' => 'https://example.test/login', 'notes' => null, ...$overrides];
}

it('lets the executive director and the secretary manage platform accounts, and no one else', function () {
    $account = app(SavePlatformAccount::class)->handle($this->director, new PlatformAccount, platformData());
    app(SavePlatformAccount::class)->handle(userWithRole(RoleName::Secretary), $account, platformData(['username' => 'other']));

    expect($account->fresh()->username)->toBe('other');

    foreach ([RoleName::BoardMember, RoleName::ProjectsManager, RoleName::ContentEditor, RoleName::Member] as $role) {
        $user = userWithRole($role);

        expect($user->can('viewAny', PlatformAccount::class))->toBeFalse()
            ->and($user->can('view', $account))->toBeFalse()
            ->and(fn () => app(SavePlatformAccount::class)->handle($user, new PlatformAccount, platformData()))->toThrow(AuthorizationException::class);
    }
});

it('takes only https login links and never a password', function () {
    expect(fn () => app(SavePlatformAccount::class)->handle($this->director, new PlatformAccount, platformData(['login_url' => 'javascript:alert(1)'])))->toThrow(ValidationException::class)
        ->and(fn () => app(SavePlatformAccount::class)->handle($this->director, new PlatformAccount, platformData(['login_url' => 'http://example.test'])))->toThrow(ValidationException::class);

    $account = app(SavePlatformAccount::class)->handle($this->director, new PlatformAccount, platformData(['password' => 'secret-value']));
    expect(json_encode($account->getAttributes()))->not->toContain('secret-value');
});

it('has no password-like column in any table of this module', function (string $table) {
    $suspicious = collect(Schema::getColumnListing($table))->filter(fn (string $column): bool => (bool) preg_match('/pass|secret|pwd|pin|token|credential/i', $column));

    expect(Schema::hasTable($table))->toBeTrue()
        ->and($suspicious->all())->toBe([]);
})->with(['register_entries', 'register_entry_alerts', 'committees', 'body_memberships', 'meeting_agenda_items', 'annual_plan_items', 'platform_accounts']);
