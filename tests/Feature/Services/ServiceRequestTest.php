<?php

use App\Enums\FormFieldType;
use App\Enums\OpeningKind;
use App\Enums\RoleName;
use App\Enums\ServiceRequestStatus;
use App\Models\Opening;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\ServiceRequestStatusChanged;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->user = User::factory()->create();
    $this->user->assignRole(RoleName::Member->value);
    $this->opening = Opening::factory()->create(['slug' => 'data-analyst']);
});

function pdfUpload(string $name = 'cv.pdf', string $extra = ''): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, pdfContents($extra));
}

it('shows the services page with the tiles of built services only', function () {
    $this->get('/services')
        ->assertOk()
        ->assertSee('خدماتنا الإلكترونية')
        ->assertSee(route('services.assembly-membership'))
        ->assertSee(route('services.jobs'))
        ->assertSee(route('services.volunteering'));
});

it('lists open published openings of each kind only', function () {
    Opening::factory()->unpublished()->create(['title' => 'وظيفة غير منشورة']);
    Opening::factory()->closed()->create(['title' => 'وظيفة مغلقة']);
    Opening::factory()->kind(OpeningKind::Volunteering)->create(['title' => 'فرصة تطوع']);

    $this->get('/services/jobs')->assertOk()->assertSee($this->opening->title)
        ->assertDontSee('وظيفة غير منشورة')->assertDontSee('وظيفة مغلقة')->assertDontSee('فرصة تطوع');
    $this->get('/services/volunteering')->assertOk()->assertSee('فرصة تطوع');
    $this->get('/services/assembly-membership')->assertOk()->assertSee(__('services.listing.membership_closed'));
    $this->get('/services/openings/data-analyst')->assertOk()->assertSee($this->opening->title);
    $this->get('/services/openings/'.Opening::where('title', 'وظيفة غير منشورة')->value('slug'))->assertNotFound();
});

it('asks guests to log in and unverified users to verify their email before applying', function () {
    $this->get('/services/openings/data-analyst/apply')->assertRedirect(route('login'));

    $unverified = User::factory()->unverified()->create();
    $this->actingAs($unverified)->get('/services/openings/data-analyst/apply')->assertRedirect(route('verification.notice'));
});

it('saves an incomplete request as a draft, then submits it complete with a private PDF', function () {
    $this->actingAs($this->user)->get('/services/openings/data-analyst/apply')->assertOk()->assertSee('نبذة');

    $this->post('/services/openings/data-analyst/apply', ['intent' => 'save', 'answers' => ['about' => '']])
        ->assertSessionHasNoErrors();

    $request = ServiceRequest::sole();
    expect($request->status)->toBe(ServiceRequestStatus::Draft)
        ->and($request->submitted_at)->toBeNull()
        ->and($request->reference)->toMatch('/^[A-Z0-9]{10}$/');
    Notification::assertNothingSent();

    // الإرسال ناقصاً يُرفض
    $this->put(route('account.requests.update', $request), ['intent' => 'submit', 'answers' => ['about' => '']])
        ->assertSessionHasErrors('answers.about');

    $this->put(route('account.requests.update', $request), ['intent' => 'submit', 'answers' => ['about' => 'محلل بيانات'], 'files' => ['cv' => pdfUpload()]])
        ->assertRedirect(route('account.requests.show', $request))
        ->assertSessionHasNoErrors();

    $request->refresh();
    expect($request->status)->toBe(ServiceRequestStatus::Received)
        ->and($request->submitted_at)->not->toBeNull()
        ->and($request->answers['about'])->toBe('محلل بيانات')
        ->and($request->files['cv']['path'])->toStartWith('service-requests/')
        ->and(Storage::disk('local')->exists($request->files['cv']['path']))->toBeTrue()
        ->and($request->events()->sole()->to_status)->toBe(ServiceRequestStatus::Received);

    Notification::assertSentTo($this->user, ServiceRequestStatusChanged::class);

    // بعد الإرسال لا يُعدَّل
    $this->put(route('account.requests.update', $request), ['intent' => 'save', 'answers' => ['about' => 'تعديل']])->assertForbidden();
});

it('refuses wrong input: an unknown option, a fake PDF, and a PDF with active content', function () {
    $this->opening->update(['form_fields' => [
        ['key' => 'city', 'label' => 'المدينة', 'type' => FormFieldType::Select->value, 'required' => true, 'options' => ['مكة المكرمة', 'جدة']],
        ['key' => 'cv', 'label' => 'السيرة الذاتية', 'type' => FormFieldType::Pdf->value, 'required' => true],
    ]]);

    $this->actingAs($this->user)
        ->post('/services/openings/data-analyst/apply', ['intent' => 'submit', 'answers' => ['city' => 'الرياض'], 'files' => ['cv' => UploadedFile::fake()->createWithContent('cv.pdf', 'ليس PDF')]])
        ->assertSessionHasErrors(['answers.city', 'files.cv']);

    $this->post('/services/openings/data-analyst/apply', ['intent' => 'submit', 'answers' => ['city' => 'جدة'], 'files' => ['cv' => pdfUpload('js.pdf', '4 0 obj << /S /JavaScript /JS (app.alert(1)) >> endobj')]])
        ->assertSessionHasErrors('files.cv');

    $this->post('/services/openings/data-analyst/apply', ['intent' => 'publish'])->assertSessionHasErrors('intent');

    expect(ServiceRequest::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('service-requests'))->toBe([]);
});

it('keeps one request per user for each opening', function () {
    $existing = ServiceRequest::factory()->for($this->user)->for($this->opening)->create();

    $this->actingAs($this->user)->get('/services/openings/data-analyst/apply')->assertRedirect(route('account.requests.show', $existing));
    $this->post('/services/openings/data-analyst/apply', ['intent' => 'save', 'answers' => ['about' => 'ثانٍ']])->assertSessionHasErrors('answers');

    expect(ServiceRequest::count())->toBe(1);
});

it('refuses applying to a closed or unpublished opening, and submitting a draft after the opening closes', function () {
    $closed = Opening::factory()->closed()->create();
    $hidden = Opening::factory()->unpublished()->create();

    $this->actingAs($this->user);
    $this->get('/services/openings/'.$closed->slug.'/apply')->assertNotFound();
    $this->post('/services/openings/'.$closed->slug.'/apply', ['intent' => 'save'])->assertSessionHasErrors('answers');
    $this->post('/services/openings/'.$hidden->slug.'/apply', ['intent' => 'save'])->assertNotFound();

    $draft = ServiceRequest::factory()->draft()->for($this->user)->for($this->opening)->create();
    $this->opening->update(['closes_at' => now()->subMinute()]);

    $this->put(route('account.requests.update', $draft), ['intent' => 'submit', 'answers' => ['about' => 'نص']])->assertSessionHasErrors('answers');
    expect($draft->fresh()->status)->toBe(ServiceRequestStatus::Draft);
});

it('lets the applicant complete a request the association sent back, even after closing', function () {
    $request = ServiceRequest::factory()->status(ServiceRequestStatus::NeedsCompletion)->for($this->user)->for($this->opening)->create();
    $this->opening->update(['closes_at' => now()->subDay()]);

    $this->actingAs($this->user)
        ->put(route('account.requests.update', $request), ['intent' => 'submit', 'answers' => ['about' => 'بعد الاستكمال']])
        ->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe(ServiceRequestStatus::Received)
        ->and($request->events()->sole()->from_status)->toBe(ServiceRequestStatus::NeedsCompletion);
});

it('lists my requests and shows replies without internal notes', function () {
    $request = ServiceRequest::factory()->for($this->user)->for($this->opening)->create(['status' => ServiceRequestStatus::UnderReview]);
    $request->events()->create(['to_status' => ServiceRequestStatus::UnderReview, 'message' => 'نراجع طلبك', 'internal_note' => 'ملاحظة سرية']);
    ServiceRequest::factory()->create(['answers' => ['about' => 'طلب شخص آخر']]);

    $this->actingAs($this->user)->get('/account/requests')
        ->assertOk()->assertSee($request->reference)->assertSee('قيد المراجعة');

    $this->get(route('account.requests.show', $request))
        ->assertOk()->assertSee('نراجع طلبك')->assertDontSee('ملاحظة سرية');
});

it('never shows, changes or serves a request to anyone but its owner', function () {
    $request = ServiceRequest::factory()->draft()->for($this->opening)->create([
        'files' => ['cv' => ['path' => 'service-requests/x.pdf', 'name' => 'cv.pdf', 'size' => 10]],
    ]);
    Storage::disk('local')->put('service-requests/x.pdf', pdfContents());

    $this->actingAs($this->user);
    $this->get(route('account.requests.show', $request))->assertNotFound();
    $this->get(route('account.requests.file', [$request, 'cv']))->assertNotFound();
    $this->put(route('account.requests.update', $request), ['intent' => 'save', 'answers' => ['about' => 'اختراق']])->assertForbidden();
    expect($request->fresh()->answers)->not->toBe(['about' => 'اختراق']);

    // ولا موظف الطلبات من صفحات صاحب الطلب
    $this->actingAs(userWithRole(RoleName::Secretary))->get(route('account.requests.show', $request))->assertNotFound();

    auth()->logout();
    $this->get(route('account.requests.show', $request))->assertRedirect(route('login'));
    $this->get(route('account.requests.file', [$request, 'cv']))->assertRedirect(route('login'));

    $this->actingAs($request->user)->get(route('account.requests.file', [$request, 'cv']))
        ->assertOk()->assertDownload('cv.pdf');
    $this->get(route('account.requests.file', [$request, 'missing']))->assertNotFound();
});

it('replaces an uploaded file and deletes the old one', function () {
    $this->actingAs($this->user)->post('/services/openings/data-analyst/apply', ['intent' => 'save', 'files' => ['cv' => pdfUpload('old.pdf')]]);
    $request = ServiceRequest::sole();
    $old = $request->files['cv']['path'];

    $this->put(route('account.requests.update', $request), ['intent' => 'save', 'files' => ['cv' => pdfUpload('new.pdf')]])->assertSessionHasNoErrors();

    $request->refresh();
    expect($request->files['cv']['name'])->toBe('new.pdf')
        ->and(Storage::disk('local')->exists($old))->toBeFalse()
        ->and(Storage::disk('local')->exists($request->files['cv']['path']))->toBeTrue();
});

it('adds my requests to the account navigation', function () {
    $this->actingAs($this->user)->get('/account')->assertOk()->assertSee(route('account.requests.index'));
});
