<?php

use App\Actions\Settings\UpdateSiteSettings;
use App\Actions\Users\ChangeUserRoles;
use App\Enums\RoleName;
use App\Filament\Pages\AssistantSettingsPage;
use App\Filament\Pages\HomeContentPage;
use App\Filament\Pages\MessagingChannels;
use App\Filament\Pages\OnlineMeetingsSettings;
use App\Filament\Pages\SiteSettings;
use App\Models\CompetitionDraw;
use App\Models\Setting;
use App\Models\User;
use App\Support\TeamsConnection;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/*
| ملاحظات المدير التنفيذي عبر صفوان (25 أكتوبر 2026): المشرف العام اطلاع فقط، والإضافة والتعديل لمديري النظام (المدير
| التنفيذي «أدمن كامل» والمسؤول التقني). والمنع في السياسات لا في الواجهة وحدها.
*/

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/**
 * سجل حقيقي لكل مورد في اللوحة من مصنعه.
 *
 * @return array<class-string, Model>
 */
function panelRecords(): array
{
    $records = [];

    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        $model = $resource::getModel();

        if (isset($records[$model]) || ! method_exists($model, 'factory')) {
            continue;
        }

        try {
            $records[$model] = $model::factory()->create();
        } catch (Throwable) {
            // بلا سجل
        }
    }

    return $records;
}

it('lets the super admin see every panel list and record, and refuses every create, update, delete and restore', function () {
    $super = userWithRole(RoleName::SuperAdmin);
    $gate = Gate::forUser($super);
    $checked = 0;

    foreach (panelRecords() as $model => $record) {
        // سجل المحادثات وسجل العمليات للاطلاع، وما لا قائمة له لا يُفحص هنا
        expect($gate->allows('viewAny', $model))->toBeTrue($model);

        foreach (['update', 'delete', 'restore', 'forceDelete'] as $ability) {
            expect($gate->allows($ability, $record))->toBeFalse($model.' '.$ability);
        }

        expect($gate->allows('create', $model))->toBeFalse($model.' create');
        $checked++;
    }

    expect($checked)->toBeGreaterThan(30);
});

it('refuses the super admin every write permission and every admin-only rule, but not reading', function () {
    $super = userWithRole(RoleName::SuperAdmin);

    foreach (['news.update', 'pages.create', 'requests.assign', 'requests.export', 'competitions.draw', 'submissions.export', 'users.update',
        'assembly_members.create', 'settings.update', 'ai_assistant.manage', 'messaging.update', 'online_meetings.update', 'retention.update',
        'accounts.update', 'announcements.create', 'account_cards.update', 'payments.refund', 'minutes.approve'] as $permission) {
        expect($super->can($permission))->toBeFalse($permission);
    }

    foreach (['news.view', 'requests.view', 'meetings.view_all', 'form_entries.view_all', 'stats.view'] as $permission) {
        expect($super->can($permission))->toBeTrue($permission);
    }

    expect($super->can('assignRoles', User::class))->toBeFalse()
        ->and($super->can('void', new CompetitionDraw))->toBeFalse();
});

it('gives the executive director and the technical admin every write, the technical settings and every role', function (RoleName $role) {
    $admin = userWithRole($role);
    $gate = Gate::forUser($admin);

    foreach (panelRecords() as $model => $record) {
        expect($gate->allows('viewAny', $model))->toBeTrue($model);
    }

    foreach (['news.update', 'requests.assign', 'settings.update', 'ai_assistant.manage', 'messaging.update', 'online_meetings.update',
        'retention.update', 'accounts.update', 'announcements.create', 'account_cards.update', 'assembly_members.create'] as $permission) {
        expect($admin->can($permission))->toBeTrue($permission);
    }

    expect($admin->can('assignRoles', User::class))->toBeTrue()
        ->and(RoleName::assignableBy($admin))->toContain(RoleName::TechnicalAdmin, RoleName::SuperAdmin, RoleName::ExecutiveDirector)
        ->and(RoleName::assignableBy($admin))->not->toContain(RoleName::AssemblyMember);
})->with([RoleName::ExecutiveDirector, RoleName::TechnicalAdmin]);

it('opens the settings pages to the super admin read-only, and refuses his save in the page and in the action', function () {
    $super = userWithRole(RoleName::SuperAdmin);
    $this->actingAs($super);

    // الزر مخفي، واستدعاء الدالة من المتصفح ($wire.save() و$wire.testConnection()) مرفوض في الصفحة قبل الإجراء
    // (مراجعة المشرف المبكرة، ١)
    foreach ([SiteSettings::class, HomeContentPage::class, AssistantSettingsPage::class, MessagingChannels::class, OnlineMeetingsSettings::class] as $page) {
        expect($page::canAccess())->toBeTrue($page);
        Livewire::test($page)->assertOk()->call('save')->assertForbidden();
    }

    foreach ([AssistantSettingsPage::class, OnlineMeetingsSettings::class] as $page) {
        Livewire::test($page)->call('testConnection')->assertForbidden();
    }

    // والأسرار المحفوظة لا تُعبّأ في حالة الصفحة التي تصل متصفحه
    enableAssistant(['api_key' => 'sk-secret-assistant-key']);
    Setting::put(TeamsConnection::SETTING, ['client_secret' => 'teams-secret-value']);

    foreach ([AssistantSettingsPage::class, OnlineMeetingsSettings::class] as $page) {
        expect(Livewire::test($page)->html())->not->toContain('sk-secret-assistant-key')->not->toContain('teams-secret-value');
    }

    expect(fn () => app(UpdateSiteSettings::class)->handle($super, ['contact' => ['email' => 'x@example.test']]))
        ->toThrow(AuthorizationException::class);

    expect(Setting::group('contact')['email'] ?? null)->not->toBe('x@example.test');
});

it('keeps the technical settings away from the content editor', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    expect(AssistantSettingsPage::canAccess())->toBeFalse()
        ->and(MessagingChannels::canAccess())->toBeFalse()
        ->and(OnlineMeetingsSettings::canAccess())->toBeFalse()
        ->and(SiteSettings::canAccess())->toBeTrue();
});

it('lets no administrator remove their own administrator role, so the portal keeps someone to run it', function (RoleName $role) {
    $admin = userWithRole($role);

    expect(fn () => app(ChangeUserRoles::class)->handle($admin, $admin, [RoleName::Member->value]))
        ->toThrow(AuthorizationException::class);

    expect($admin->fresh()->hasRole($role->value))->toBeTrue();
})->with([RoleName::ExecutiveDirector, RoleName::TechnicalAdmin]);
