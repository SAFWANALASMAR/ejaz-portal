<?php

use App\Enums\MeetingBody;
use App\Enums\RoleName;
use App\Filament\Resources\AnnualPlanItems\AnnualPlanItemResource;
use App\Filament\Resources\AnnualPlanItems\Pages\CreateAnnualPlanItem;
use App\Filament\Resources\BodyMemberships\BodyMembershipResource;
use App\Filament\Resources\Committees\CommitteeResource;
use App\Filament\Resources\Meetings\MeetingResource;
use App\Filament\Resources\Meetings\Pages\CreateMeeting;
use App\Filament\Resources\Meetings\Pages\ListMeetings;
use App\Filament\Resources\Meetings\Pages\ViewMeeting;
use App\Filament\Resources\Meetings\RelationManagers\AgendaItemsRelationManager;
use App\Filament\Resources\PlatformAccounts\Pages\ViewPlatformAccount;
use App\Filament\Resources\PlatformAccounts\PlatformAccountResource;
use App\Filament\Resources\RegisterEntries\Pages\CreateRegisterEntry;
use App\Filament\Resources\RegisterEntries\Pages\ListRegisterEntries;
use App\Filament\Resources\RegisterEntries\Pages\ViewRegisterEntry;
use App\Filament\Resources\RegisterEntries\RegisterEntryResource;
use App\Filament\Widgets\AnnualPlanWidget;
use App\Filament\Widgets\RegistersOverviewWidget;
use App\Models\AnnualPlanItem;
use App\Models\Committee;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Models\PlatformAccount;
use App\Models\RegisterEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| شاشات السجلات والمجالس والخطة وحسابات المنصات في اللوحة بصلاحياتها، وعناصر الرئيسية، وتسجيل الاطلاع.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->director = userWithRole(RoleName::ExecutiveDirector);
    $this->secretary = userWithRole(RoleName::Secretary);
    $this->board = userWithRole(RoleName::BoardMember);
    $this->projects = userWithRole(RoleName::ProjectsManager);
});

it('opens each screen to its roles and keeps the others out', function () {
    $screens = [
        RegisterEntryResource::getUrl('index') => ['director' => 200, 'secretary' => 200, 'board' => 200, 'projects' => 403],
        RegisterEntryResource::getUrl('create') => ['director' => 200, 'secretary' => 200, 'board' => 403, 'projects' => 403],
        CommitteeResource::getUrl('index') => ['director' => 200, 'secretary' => 200, 'board' => 200, 'projects' => 403],
        CommitteeResource::getUrl('create') => ['director' => 200, 'secretary' => 200, 'board' => 403, 'projects' => 403],
        BodyMembershipResource::getUrl('index') => ['director' => 200, 'secretary' => 200, 'board' => 200, 'projects' => 403],
        AnnualPlanItemResource::getUrl('index') => ['director' => 200, 'secretary' => 403, 'board' => 200, 'projects' => 200],
        AnnualPlanItemResource::getUrl('create') => ['director' => 200, 'secretary' => 403, 'board' => 403, 'projects' => 200],
        PlatformAccountResource::getUrl('index') => ['director' => 200, 'secretary' => 200, 'board' => 403, 'projects' => 403],
        MeetingResource::getUrl('index') => ['director' => 200, 'secretary' => 200, 'board' => 200, 'projects' => 403],
    ];

    foreach ($screens as $url => $expected) {
        foreach ($expected as $who => $status) {
            $this->actingAs($this->{$who})->get($url)->assertStatus($status);
        }

        $this->actingAs(userWithRole(RoleName::ContentEditor))->get($url)->assertForbidden();
    }
});

it('creates an entry from the panel with the default alert window, and logs opening it', function () {
    $this->actingAs($this->secretary);

    Livewire::test(CreateRegisterEntry::class)
        ->assertSchemaStateSet(['alert_days' => config('registers.default_alert_days')])
        ->fillForm(['type' => 'contract', 'title' => 'عقد تجريبي', 'issued_on' => '2026-01-01', 'expires_on' => '2027-01-01', 'alert_days' => 15])
        ->call('create')
        ->assertHasNoFormErrors();

    $entry = RegisterEntry::sole();
    expect($entry->alert_days)->toBe(15)->and($entry->created_by)->toBe($this->secretary->id);

    $this->actingAs($this->board);
    Livewire::test(ViewRegisterEntry::class, ['record' => $entry->uuid])->assertOk()->assertSee('عقد تجريبي')->assertActionHidden('edit');
    expect(Activity::where('event', 'viewed')->whereMorphedTo('subject', $entry)->sole()->causer_id)->toBe($this->board->id);
});

it('filters the list by type and status', function () {
    $this->travelTo(Carbon::parse('2026-10-10 09:00:00', 'Asia/Riyadh'));
    $expired = RegisterEntry::factory()->create(['type' => 'license', 'expires_on' => '2026-10-01']);
    $valid = RegisterEntry::factory()->create(['type' => 'contract', 'expires_on' => '2027-10-01']);

    $this->actingAs($this->director);
    Livewire::test(ListRegisterEntries::class)
        ->assertCanSeeTableRecords([$expired, $valid])
        ->filterTable('status', 'expired')
        ->assertCanSeeTableRecords([$expired])
        ->assertCanNotSeeTableRecords([$valid])
        ->resetTableFilters()
        ->filterTable('type', 'contract')
        ->assertCanSeeTableRecords([$valid])
        ->assertCanNotSeeTableRecords([$expired]);
});

it('shows the registers widget with counts per type to those who see the registers only', function () {
    $this->travelTo(Carbon::parse('2026-10-10 09:00:00', 'Asia/Riyadh'));
    RegisterEntry::factory()->create(['type' => 'license', 'expires_on' => '2026-10-01', 'title' => 'ترخيص منتهٍ']);
    RegisterEntry::factory()->create(['type' => 'license', 'expires_on' => '2026-10-15', 'alert_days' => 30, 'title' => 'ترخيص قريب']);

    $this->actingAs($this->board);
    expect(RegistersOverviewWidget::canView())->toBeTrue();
    Livewire::test(RegistersOverviewWidget::class)->assertOk()->assertSee('ترخيص قريب')->assertSee('ترخيص منتهٍ')->assertSee(__('registers.types.license'));

    $this->actingAs($this->projects);
    expect(RegistersOverviewWidget::canView())->toBeFalse()
        ->and(AnnualPlanWidget::canView())->toBeTrue();
    Livewire::test(AnnualPlanWidget::class)->assertOk();
});

it('lets the projects manager create a plan item from the panel', function () {
    $this->actingAs($this->projects);

    Livewire::test(CreateAnnualPlanItem::class)
        ->fillForm(['year' => 2026, 'title' => 'نشاط تجريبي', 'department' => 'إدارة تجريبية', 'type' => 'نوع', 'target_beneficiaries' => 50])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(AnnualPlanItem::sole()->created_by)->toBe($this->projects->id);
});

it('shows the body on meetings and hides committee meetings from board members outside it', function () {
    $committee = Committee::factory()->create(['name' => 'لجنة اختبار']);
    $committeeMeeting = Meeting::factory()->create(['body' => MeetingBody::Committee, 'committee_id' => $committee->id]);
    $assembly = Meeting::factory()->create();

    $this->actingAs($this->board);
    Livewire::test(ListMeetings::class)->assertCanSeeTableRecords([$assembly])->assertCanNotSeeTableRecords([$committeeMeeting]);
    $this->get(MeetingResource::getUrl('view', ['record' => $committeeMeeting]))->assertNotFound();

    $this->actingAs($this->director);
    Livewire::test(ListMeetings::class)->assertCanSeeTableRecords([$assembly, $committeeMeeting]);
});

it('manages agenda items from the meeting page for the secretary, read-only for others', function () {
    $meeting = Meeting::factory()->held()->create(['body' => MeetingBody::Board]);
    $item = MeetingAgendaItem::factory()->create(['meeting_id' => $meeting->id, 'title' => 'بند ظاهر']);

    $this->actingAs($this->secretary);
    Livewire::test(AgendaItemsRelationManager::class, ['ownerRecord' => $meeting, 'pageClass' => ViewMeeting::class])
        ->assertCanSeeTableRecords([$item])
        ->assertTableActionVisible('create')
        ->callTableAction('create', data: ['position' => 2, 'title' => 'بند جديد', 'decision' => 'قرار', 'status' => 'not_started'])
        ->assertHasNoTableActionErrors();
    expect($meeting->agendaItems()->count())->toBe(2);

    $this->actingAs($this->board);
    Livewire::test(AgendaItemsRelationManager::class, ['ownerRecord' => $meeting, 'pageClass' => ViewMeeting::class])
        ->assertCanSeeTableRecords([$item])
        ->assertTableActionHidden('create')
        ->assertTableActionHidden('edit', $item);
});

it('logs opening a platform account page', function () {
    $account = PlatformAccount::factory()->create(['platform' => 'منصة الاختبار']);

    $this->actingAs($this->secretary);
    Livewire::test(ViewPlatformAccount::class, ['record' => $account->getKey()])->assertOk()->assertSee('منصة الاختبار');

    expect(Activity::where('event', 'viewed')->whereMorphedTo('subject', $account)->sole()->causer_id)->toBe($this->secretary->id);
});

it('makes the secretary choose the body of a new meeting, with no preselected general assembly', function () {
    $this->actingAs($this->secretary);

    Livewire::test(CreateMeeting::class)
        ->assertSchemaStateSet(['body' => null])
        ->fillForm(['title' => 'اجتماع تجريبي'])
        ->call('create')
        ->assertHasFormErrors(['body' => 'required']);
});
