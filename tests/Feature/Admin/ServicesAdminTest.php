<?php

use App\Actions\Services\AssignServiceRequest;
use App\Actions\Services\ChangeServiceRequestStatus;
use App\Enums\FormFieldType;
use App\Enums\OpeningKind;
use App\Enums\RoleName;
use App\Enums\ServiceRequestStatus;
use App\Filament\Exports\ServiceRequestExporter;
use App\Filament\Resources\Openings\OpeningResource;
use App\Filament\Resources\Openings\Pages\CreateOpening;
use App\Filament\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Filament\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\Opening;
use App\Models\ServiceRequest;
use App\Notifications\ServiceRequestStatusChanged;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->secretary = userWithRole(RoleName::Secretary);
});

it('opens the requests and openings screens to the secretary only', function () {
    $request = ServiceRequest::factory()->create();

    $this->actingAs($this->secretary)->get(ServiceRequestResource::getUrl('index'))->assertOk();
    $this->actingAs($this->secretary)->get(ServiceRequestResource::getUrl('view', ['record' => $request]))->assertOk()->assertSee($request->reference);
    $this->actingAs($this->secretary)->get(OpeningResource::getUrl('create'))->assertOk();

    foreach ([RoleName::ContentEditor, RoleName::BoardMember, RoleName::ProgramManager] as $role) {
        $this->actingAs(userWithRole($role))->get(ServiceRequestResource::getUrl('index'))->assertForbidden();
        $this->actingAs(userWithRole($role))->get(OpeningResource::getUrl('index'))->assertForbidden();
    }
});

it('hides drafts from staff, even the super admin', function () {
    $draft = ServiceRequest::factory()->draft()->create();
    $sent = ServiceRequest::factory()->create();

    $this->actingAs($this->secretary);
    Livewire::test(ListServiceRequests::class)->assertCanSeeTableRecords([$sent])->assertCanNotSeeTableRecords([$draft]);
    $this->get(ServiceRequestResource::getUrl('view', ['record' => $draft]))->assertNotFound();

    $admin = userWithRole(RoleName::SuperAdmin);
    expect($admin->can('view', $draft))->toBeFalse()
        ->and($admin->can('update', $draft))->toBeFalse()
        ->and($admin->can('view', $sent))->toBeTrue();
});

it('creates an opening with its form fields and logs it', function () {
    $this->actingAs($this->secretary);

    Livewire::test(CreateOpening::class)
        ->fillForm([
            'title' => 'طلب تطوع عام',
            'slug' => 'general-volunteering',
            'kind' => OpeningKind::Volunteering->value,
            'is_published' => true,
            'form_fields' => [
                ['label' => 'مجال التطوع', 'key' => 'field', 'type' => FormFieldType::Select->value, 'required' => true, 'options' => ['إعلامي', 'تنظيمي']],
                ['label' => 'نبذة', 'key' => 'about', 'type' => FormFieldType::Textarea->value, 'required' => false],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $opening = Opening::where('slug', 'general-volunteering')->sole();

    expect($opening->fields())->toHaveCount(2)
        ->and($opening->fields()[0]['options'])->toBe(['إعلامي', 'تنظيمي'])
        ->and(Activity::whereMorphedTo('subject', $opening)->where('event', 'created')->exists())->toBeTrue();
});

it('refuses repeated or malformed field keys', function () {
    $this->actingAs($this->secretary);

    Livewire::test(CreateOpening::class)
        ->fillForm([
            'title' => 'وظيفة', 'slug' => 'job', 'kind' => OpeningKind::Employment->value,
            'form_fields' => [
                ['label' => 'أ', 'key' => 'Bad Key', 'type' => FormFieldType::Text->value],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(Opening::count())->toBe(0);
});

it('moves a request through allowed states, logs each change, and notifies the applicant with the reply only', function () {
    $request = ServiceRequest::factory()->create();
    $this->actingAs($this->secretary);

    Livewire::test(ViewServiceRequest::class, ['record' => $request->getRouteKey()])
        ->callAction(TestAction::make('changeStatus'), ['status' => ServiceRequestStatus::NeedsCompletion->value, 'message' => 'أرفق السيرة الذاتية', 'internal_note' => 'للمتابعة'])
        ->assertHasNoFormErrors();

    $request->refresh();
    $event = $request->events()->sole();

    expect($request->status)->toBe(ServiceRequestStatus::NeedsCompletion)
        ->and($event->actor_id)->toBe($this->secretary->id)
        ->and($event->from_status)->toBe(ServiceRequestStatus::Received)
        ->and($event->internal_note)->toBe('للمتابعة')
        ->and(Activity::whereMorphedTo('subject', $request)->where('event', 'updated')->exists())->toBeTrue();

    Notification::assertSentTo($request->user, ServiceRequestStatusChanged::class, function (ServiceRequestStatusChanged $notification) use ($request) {
        $mail = $notification->toMail($request->user);

        return str_contains(implode(' ', $mail->introLines), 'أرفق السيرة الذاتية') && ! str_contains(implode(' ', $mail->introLines), 'للمتابعة');
    });
});

it('refuses a transition the current state does not allow', function () {
    $request = ServiceRequest::factory()->status(ServiceRequestStatus::Closed)->create();

    expect(fn () => app(ChangeServiceRequestStatus::class)->handle($this->secretary, $request, ServiceRequestStatus::Accepted))
        ->toThrow(ValidationException::class);
    expect($request->fresh()->status)->toBe(ServiceRequestStatus::Closed);
});

it('refuses status changes from users without the permission', function () {
    $request = ServiceRequest::factory()->create();

    expect(fn () => app(ChangeServiceRequestStatus::class)->handle(userWithRole(RoleName::ContentEditor), $request, ServiceRequestStatus::UnderReview))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(ChangeServiceRequestStatus::class)->handle($request->user, $request, ServiceRequestStatus::Accepted))
        ->toThrow(AuthorizationException::class);
});

it('grants assembly membership on acceptance by the super admin only, and logs the role', function () {
    $opening = Opening::factory()->kind(OpeningKind::Membership)->create();
    $request = ServiceRequest::factory()->for($opening)->status(ServiceRequestStatus::UnderReview)->create();

    expect(fn () => app(ChangeServiceRequestStatus::class)->handle($this->secretary, $request, ServiceRequestStatus::Accepted))
        ->toThrow(AuthorizationException::class);
    expect($request->user->fresh()->hasRole(RoleName::AssemblyMember->value))->toBeFalse();

    $admin = userWithRole(RoleName::SuperAdmin);
    app(ChangeServiceRequestStatus::class)->handle($admin, $request, ServiceRequestStatus::Accepted);

    expect($request->fresh()->status)->toBe(ServiceRequestStatus::Accepted)
        ->and($request->user->fresh()->hasRole(RoleName::AssemblyMember->value))->toBeTrue()
        ->and(Activity::where('event', 'roles_changed')->whereMorphedTo('subject', $request->user)->where('causer_id', $admin->id)->exists())->toBeTrue();
});

it('assigns a request to staff who can handle it, and refuses others', function () {
    $request = ServiceRequest::factory()->create();
    $colleague = userWithRole(RoleName::Secretary);
    $this->actingAs($this->secretary);

    Livewire::test(ViewServiceRequest::class, ['record' => $request->getRouteKey()])
        ->callAction(TestAction::make('assign'), ['assigned_to' => $colleague->id])
        ->assertHasNoFormErrors();

    expect($request->fresh()->assigned_to)->toBe($colleague->id)
        ->and(AssignServiceRequest::candidates())->toHaveKey($colleague->id)
        ->and(AssignServiceRequest::candidates())->not->toHaveKey($request->user_id);

    expect(fn () => app(AssignServiceRequest::class)->handle($this->secretary, $request, $request->user))
        ->toThrow(ValidationException::class);
});

it('downloads an attachment from the panel for staff who can view the request', function () {
    Storage::disk('local')->put('service-requests/cv.pdf', pdfContents());
    $request = ServiceRequest::factory()->create(['files' => ['cv' => ['path' => 'service-requests/cv.pdf', 'name' => 'cv.pdf', 'size' => 10]]]);

    $this->actingAs($this->secretary);
    Livewire::test(ViewServiceRequest::class, ['record' => $request->getRouteKey()])
        ->callAction(TestAction::make('download_cv'))
        ->assertFileDownloaded($request->reference.'-cv.pdf');
});

it('exports submitted requests to Excel on the private disk for the requester only', function () {
    ServiceRequest::factory()->count(2)->create();
    ServiceRequest::factory()->draft()->create();
    $this->actingAs($this->secretary);

    Livewire::test(ListServiceRequests::class)->callAction('export')->assertHasNoFormErrors();

    $export = Export::sole();
    expect($export->exporter)->toBe(ServiceRequestExporter::class)
        ->and($export->file_disk)->toBe('local')
        ->and($export->total_rows)->toBe(2);

    $this->actingAs(userWithRole(RoleName::Secretary))
        ->get(route('filament.exports.download', ['export' => $export, 'format' => 'xlsx']))
        ->assertForbidden();
});

it('hides the export from staff without the export permission', function () {
    $this->actingAs(userWithRole(RoleName::SuperAdmin));
    Livewire::test(ListServiceRequests::class)->assertActionVisible('export');

    expect(userWithRole(RoleName::ContentEditor)->can('export', ServiceRequest::class))->toBeFalse();
});
