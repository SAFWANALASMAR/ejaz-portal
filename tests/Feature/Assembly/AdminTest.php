<?php

use App\Enums\MeetingBody;
use App\Enums\MeetingStatus;
use App\Enums\RoleName;
use App\Filament\Resources\AssemblyFiles\AssemblyFileResource;
use App\Filament\Resources\AssemblyFiles\Pages\CreateAssemblyFile;
use App\Filament\Resources\AssemblyFiles\Pages\ListAssemblyFiles;
use App\Filament\Resources\AssemblyMemberships\AssemblyMembershipResource;
use App\Filament\Resources\AssemblyMemberships\Pages\CreateAssemblyMembership;
use App\Filament\Resources\Meetings\MeetingResource;
use App\Filament\Resources\Meetings\Pages\CreateMeeting;
use App\Filament\Resources\Meetings\Pages\EditMeeting;
use App\Filament\Resources\Meetings\Pages\ViewMeeting;
use App\Models\AssemblyFile;
use App\Models\AssemblyMembership;
use App\Models\Meeting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| شاشات الجمعية العمومية في اللوحة: السكرتير يدير الاجتماعات والملفات، والمجلس يطّلع ويعتمد المحاضر،
| والمشرف العام وحده يسجّل الأعضاء.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->secretary = userWithRole(RoleName::Secretary);
    $this->board = userWithRole(RoleName::BoardMember);
});

it('opens each screen to its roles and keeps the others out', function () {
    $this->actingAs($this->secretary);
    $this->get(MeetingResource::getUrl('index'))->assertOk();
    $this->get(MeetingResource::getUrl('create'))->assertOk();
    $this->get(AssemblyFileResource::getUrl('create'))->assertOk();
    $this->get(AssemblyMembershipResource::getUrl('index'))->assertOk();
    $this->get(AssemblyMembershipResource::getUrl('create'))->assertForbidden();

    $this->actingAs($this->board);
    $this->get(MeetingResource::getUrl('index'))->assertOk();
    $this->get(MeetingResource::getUrl('create'))->assertForbidden();
    $this->get(AssemblyFileResource::getUrl('index'))->assertOk();
    $this->get(AssemblyFileResource::getUrl('create'))->assertForbidden();

    $this->actingAs(userWithRole(RoleName::TechnicalAdmin))->get(AssemblyMembershipResource::getUrl('create'))->assertOk();

    foreach ([userWithRole(RoleName::ContentEditor), userWithRole(RoleName::ProgramManager)] as $user) {
        $this->actingAs($user)->get(MeetingResource::getUrl('index'))->assertForbidden();
        $this->actingAs($user)->get(AssemblyMembershipResource::getUrl('index'))->assertForbidden();
    }

    // عضو الجمعية العمومية لا يدخل اللوحة أصلاً
    $this->actingAs(AssemblyMembership::factory()->create()->user)->get(MeetingResource::getUrl('index'))->assertForbidden();
});

it('lets the secretary schedule a meeting with a PDF attachment from the panel, and edit it', function () {
    $this->actingAs($this->secretary);

    Livewire::test(CreateMeeting::class)
        ->fillForm([
            'title' => 'الاجتماع العادي التجريبي',
            'body' => MeetingBody::GeneralAssembly->value,
            'type' => 'ordinary',
            'starts_at' => now()->addWeek()->format('Y-m-d H:i'),
            'ends_at' => now()->addWeek()->addHours(2)->format('Y-m-d H:i'),
            'location' => 'قاعة تجريبية',
            'attachments' => [UploadedFile::fake()->createWithContent('جدول الأعمال.pdf', pdfContents())],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $meeting = Meeting::sole();
    expect($meeting->attachments)->toHaveCount(1)
        ->and($meeting->attachments[0]['name'])->toBe('جدول الأعمال.pdf')
        ->and($meeting->attachments[0]['path'])->toStartWith(Meeting::ATTACHMENTS_DIRECTORY.'/');

    // التعديل يفتح المرفق المحفوظ ويبقيه باسمه ومساره
    Livewire::test(EditMeeting::class, ['record' => $meeting->uuid])
        ->fillForm(['location' => 'قاعة أخرى'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($meeting->fresh()->location)->toBe('قاعة أخرى')
        ->and($meeting->fresh()->attachments)->toEqual($meeting->attachments);
});

it('records attendance, marks the meeting held, and takes the minutes from upload to approval', function () {
    $meeting = Meeting::factory()->create(['starts_at' => now()->subHour()]);
    $member = AssemblyMembership::factory()->create()->user;

    $this->actingAs($this->secretary);
    Livewire::test(ViewMeeting::class, ['record' => $meeting->uuid])
        ->callAction('markHeld')
        ->callAction('recordAttendance', ['attendees' => [$member->id]])
        ->assertHasNoActionErrors();

    expect($meeting->fresh()->status)->toBe(MeetingStatus::Held)
        ->and($meeting->attendees()->pluck('users.id')->all())->toBe([$member->id]);

    Livewire::test(ViewMeeting::class, ['record' => $meeting->uuid])
        ->callAction('uploadMinutes', ['minutes_file' => UploadedFile::fake()->createWithContent('minutes.pdf', pdfContents())])
        ->assertHasNoActionErrors()
        ->assertActionHidden('approveMinutes');

    expect($meeting->fresh()->hasDraftMinutes())->toBeTrue();

    $this->actingAs($this->board);
    Livewire::test(ViewMeeting::class, ['record' => $meeting->uuid])
        ->assertActionHidden('uploadMinutes')
        ->callAction('downloadMinutes')
        ->assertFileDownloaded()
        ->callAction('approveMinutes');

    expect($meeting->fresh()->hasApprovedMinutes())->toBeTrue()
        ->and(Activity::where('event', 'file_downloaded')->where('causer_id', $this->board->id)->exists())->toBeTrue();
});

it('lets the super admin register a member from the panel', function () {
    $user = User::factory()->create(['name' => 'عضو تجريبي']);
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));

    Livewire::test(CreateAssemblyMembership::class)
        ->fillForm(['user_id' => $user->id, 'status' => 'active', 'joined_on' => '2026-02-01'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($user->fresh()->hasRole(RoleName::AssemblyMember->value))->toBeTrue();
});

it('lets the secretary add a members file and download it from the panel, logging the download', function () {
    $this->actingAs($this->secretary);

    Livewire::test(CreateAssemblyFile::class)
        ->fillForm([
            'title' => 'القوائم المالية التجريبية',
            'category' => 'financial_statements',
            'audience' => 'members',
            'file' => [UploadedFile::fake()->createWithContent('statements.pdf', pdfContents())],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $file = AssemblyFile::sole();

    Livewire::test(ListAssemblyFiles::class)
        ->callAction(TestAction::make('downloadFile')->table($file))
        ->assertFileDownloaded();

    expect(Activity::where('event', 'file_downloaded')->whereMorphedTo('subject', $file)->sole()->causer_id)->toBe($this->secretary->id);
});
