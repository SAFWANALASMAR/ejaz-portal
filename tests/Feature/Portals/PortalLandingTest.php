<?php

use App\Enums\RoleName;
use App\Filament\Resources\ArchiveItems\ArchiveItemResource;
use App\Filament\Resources\AssemblyFiles\AssemblyFileResource;
use App\Filament\Resources\Competitions\CompetitionResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\FormTemplates\FormTemplateResource;
use App\Filament\Resources\Meetings\MeetingResource;
use App\Filament\Resources\Programs\ProgramResource;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\PortalWidget;
use App\Models\AssemblyFile;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\Program;
use App\Models\User;
use App\Support\Portals;
use Livewire\Livewire;

/**
 * مدخل كل بوابة بعد الدخول (requirements/12 §12): من يدخل اللوحة إلى /admin، والمشترك إلى لوحته.
 * وعنصر «بوابتي» يعرض قدرات الفئة بنصوصها، وكل قدرة رابط إلى شاشتها لمن يملك فتحها.
 */
it('sends a member to the account area after login', function () {
    $member = User::factory()->create(['email' => 'member@example.test']);
    $member->assignRole(RoleName::Member->value);

    $this->post('/login', ['login' => 'member@example.test', 'password' => 'password'])->assertRedirect('/account');
});

it('sends staff, the board and reviewers to the admin panel after the two-factor code', function (RoleName $role) {
    $user = userWithRole($role, ['email' => 'staff@example.test']);

    $this->post('/login', ['login' => 'staff@example.test', 'password' => 'password'])->assertRedirect('/two-factor-challenge');

    $recovery = json_decode(decrypt($user->two_factor_recovery_codes), true)[0];

    $this->post('/two-factor-challenge', ['recovery_code' => $recovery])->assertRedirect('/admin');
})->with([RoleName::ExecutiveDirector, RoleName::Accountant, RoleName::BoardMember, RoleName::Reviewer, RoleName::Secretary]);

it('sends staff without two-factor yet to the panel, which asks them to set it up', function () {
    $user = User::factory()->create(['email' => 'new.staff@example.test']);
    $user->assignRole(RoleName::FinancialManager->value);

    $this->post('/login', ['login' => 'new.staff@example.test', 'password' => 'password'])->assertRedirect('/admin');
    $this->get('/admin')->assertRedirect(route('account.security'));
});

it('keeps the page a member asked for before logging in', function () {
    $member = User::factory()->create(['email' => 'member@example.test']);
    $member->assignRole(RoleName::Member->value);

    $this->get('/account/profile')->assertRedirect('/login');
    $this->post('/login', ['login' => 'member@example.test', 'password' => 'password'])->assertRedirect('/account/profile');
});

it('keeps members and assembly members out of the admin panel', function (RoleName $role) {
    $this->actingAs(userWithRole($role))->get('/admin')->assertForbidden();
})->with([RoleName::Member, RoleName::AssemblyMember]);

it('opens the panel dashboard for every staff role', function (RoleName $role) {
    $this->actingAs(userWithRole($role))->get('/admin')->assertOk();
})->with([RoleName::ExecutiveDirector, RoleName::FinancialManager, RoleName::Accountant, RoleName::ProjectsManager, RoleName::MarketingManager, RoleName::Secretary, RoleName::BoardMember, RoleName::Reviewer]);

it('shows each portal its capabilities in the requirement wording', function (RoleName $role, string $portal, array $texts) {
    $this->actingAs(userWithRole($role));

    $widget = Livewire::test(PortalWidget::class)->assertSee(__('portals.'.$portal.'.title'));

    foreach ($texts as $text) {
        $widget->assertSee($text);
    }
})->with([
    'board' => [RoleName::BoardMember, 'board', ['الاطلاع على الاجتماعات', 'الاطلاع على التقارير', 'الاطلاع على الملفات المخصصة لهم']],
    'executive director' => [RoleName::ExecutiveDirector, 'executive', ['إدارة الموظفين', 'إدارة البرامج', 'إدارة المسابقات', 'إدارة الملفات', 'الإحصائيات العامة']],
    'employees' => [RoleName::Accountant, 'employees', ['رفع الملفات', 'تسجيل الحضور', 'إدارة النماذج', 'متابعة البرامج']],
    'reviewers' => [RoleName::Reviewer, 'reviewers', ['تحكيم المشاركات المسندة إليك']],
]);

it('links each capability to the screen the role may open, and nothing else', function () {
    $links = fn (User $user): array => collect(Portals::for($user))->flatMap(fn (array $portal) => collect($portal['items'])->pluck('url'))->filter()->unique()->sort()->values()->all();

    expect($links(userWithRole(RoleName::BoardMember)))->toBe(collect([MeetingResource::getUrl(), DocumentResource::getUrl(), AssemblyFileResource::getUrl()])->sort()->values()->all())
        ->and($links(userWithRole(RoleName::Accountant)))->toBe(collect([ArchiveItemResource::getUrl(), ProgramResource::getUrl(), FormTemplateResource::getUrl()])->sort()->values()->all())
        ->and($links(userWithRole(RoleName::ExecutiveDirector)))->toContain(UserResource::getUrl(), ProgramResource::getUrl(), CompetitionResource::getUrl(), ArchiveItemResource::getUrl())
        ->and($links(userWithRole(RoleName::Reviewer)))->toBe([ReviewResource::getUrl()])
        ->and(Portals::for(userWithRole(RoleName::ContentEditor)))->toBe([])
        ->and(Portals::for(userWithRole(RoleName::Member)))->toBe([]);
});

it('hides the portal widget from roles outside the five portals', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    expect(PortalWidget::canView())->toBeFalse();
});

it('shows each role only its navigation', function (RoleName $role, array $visible, array $hidden) {
    $this->actingAs(userWithRole($role));

    foreach ($visible as $resource) {
        expect($resource::canAccess())->toBeTrue($resource);
    }

    foreach ($hidden as $resource) {
        expect($resource::canAccess())->toBeFalse($resource);
    }
})->with([
    'board' => [RoleName::BoardMember, [MeetingResource::class, DocumentResource::class, AssemblyFileResource::class], [UserResource::class, ProgramResource::class]],
    'executive director' => [RoleName::ExecutiveDirector, [UserResource::class, ProgramResource::class, CompetitionResource::class, ArchiveItemResource::class, AssemblyFileResource::class], [MeetingResource::class, DocumentResource::class]],
    'employee' => [RoleName::MarketingManager, [ArchiveItemResource::class, ProgramResource::class, FormTemplateResource::class], [UserResource::class, CompetitionResource::class, MeetingResource::class, DocumentResource::class]],
    'reviewer' => [RoleName::Reviewer, [ReviewResource::class], [UserResource::class, ProgramResource::class, ArchiveItemResource::class]],
]);

it('keeps the board to read-only screens', function () {
    $board = userWithRole(RoleName::BoardMember);
    $meeting = Meeting::factory()->create();
    $document = Document::factory()->create();
    $file = AssemblyFile::factory()->forBoard()->create();
    $program = Program::factory()->create();

    expect($board->can('view', $meeting))->toBeTrue()
        ->and($board->can('update', $meeting))->toBeFalse()
        ->and($board->can('delete', $meeting))->toBeFalse()
        ->and($board->can('update', $document))->toBeFalse()
        ->and($board->can('delete', $document))->toBeFalse()
        ->and($board->can('download', $file))->toBeTrue()
        ->and($board->can('update', $file))->toBeFalse()
        ->and($board->can('update', $program))->toBeFalse();

    $this->actingAs($board)->get(MeetingResource::getUrl('index'))->assertOk();
    $this->actingAs($board)->get(MeetingResource::getUrl('create'))->assertForbidden();
    $this->actingAs($board)->get(DocumentResource::getUrl('create'))->assertForbidden();
});
