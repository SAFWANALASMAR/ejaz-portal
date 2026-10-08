<?php

use App\Enums\RegistrationStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Opening;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\AccountNavigation;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('shows my upcoming programs and latest requests, with counts on their cards', function () {
    $soon = Program::factory()->create(['title' => 'برنامج قريب', 'starts_at' => now()->addDays(2)]);
    $later = Program::factory()->create(['title' => 'برنامج لاحق', 'starts_at' => now()->addDays(9)]);
    $past = Program::factory()->create(['title' => 'برنامج مضى', 'starts_at' => now()->subDay(), 'registration_opens_at' => now()->subDays(5)]);
    ProgramRegistration::factory()->for($later)->for($this->user)->create();
    ProgramRegistration::factory()->waitlisted()->for($soon)->for($this->user)->create();
    ProgramRegistration::factory()->for($past)->for($this->user)->create();
    ProgramRegistration::factory()->cancelled()->for(Program::factory()->create(['title' => 'برنامج ملغى']))->for($this->user)->create();

    $request = ServiceRequest::factory()->for($this->user)->for(Opening::factory()->create(['title' => 'وظيفة محلل']))->create(['status' => ServiceRequestStatus::UnderReview]);

    $response = $this->actingAs($this->user)->get('/account')->assertOk()
        ->assertSeeInOrder(['برنامج قريب', 'برنامج لاحق'])
        ->assertDontSee('برنامج مضى')->assertDontSee('برنامج ملغى')
        ->assertSee(RegistrationStatus::Waitlisted->getLabel())
        ->assertSee('وظيفة محلل')->assertSee($request->reference)->assertSee(ServiceRequestStatus::UnderReview->getLabel());

    expect($response->viewData('summary')['upcoming_count'])->toBe(2)
        ->and($response->viewData('summary')['open_requests'])->toBe(1);
});

it('never shows another user\'s programs or requests', function () {
    ProgramRegistration::factory()->for(Program::factory()->create(['title' => 'برنامج غيري']))->create();
    ServiceRequest::factory()->for(Opening::factory()->create(['title' => 'طلب غيري']))->create();

    $this->actingAs($this->user)->get('/account')->assertOk()
        ->assertDontSee('برنامج غيري')->assertDontSee('طلب غيري')
        ->assertSee(__('account.summary.upcoming_empty'))->assertSee(__('account.summary.requests_empty'));
});

it('flags a request sent back for completion and an unsent draft of an open opening only', function () {
    $back = ServiceRequest::factory()->for($this->user)->status(ServiceRequestStatus::NeedsCompletion)->create();
    ServiceRequest::factory()->draft()->for($this->user)->for(Opening::factory()->create(['title' => 'فرصة مفتوحة']))->create();
    ServiceRequest::factory()->draft()->for($this->user)->for(Opening::factory()->closed()->create(['title' => 'فرصة مغلقة']))->create();

    $this->actingAs($this->user)->get('/account')->assertOk()
        ->assertSee(__('account.summary.attention'))
        ->assertSee($back->reference)
        ->assertSee(__('account.summary.open_draft', ['opening' => 'فرصة مفتوحة']))
        ->assertDontSee(__('account.summary.open_draft', ['opening' => 'فرصة مغلقة']));

    $this->actingAs(User::factory()->create())->get('/account')->assertDontSee(__('account.summary.attention'));
});

it('keeps the number of queries constant however many records I have', function () {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->user)->get('/account')->assertOk();

        return count(DB::getQueryLog());
    };

    ProgramRegistration::factory()->for($this->user)->create();
    ServiceRequest::factory()->for($this->user)->create();
    ServiceRequest::factory()->for($this->user)->status(ServiceRequestStatus::NeedsCompletion)->create();
    $count(); // تحميل أول مرة (ذاكرة الصلاحيات)
    $few = $count();

    ProgramRegistration::factory()->count(5)->for($this->user)->create();
    ServiceRequest::factory()->count(5)->for($this->user)->create();
    ServiceRequest::factory()->count(3)->for($this->user)->status(ServiceRequestStatus::NeedsCompletion)->create();

    expect($count())->toBe($few);
});

it('does not nudge a draft of a withdrawn opening, and keeps personal pages out of caches', function () {
    $opening = Opening::factory()->create(['title' => 'فرصة سُحبت']);
    ServiceRequest::factory()->draft()->for($this->user)->for($opening)->create();
    $opening->delete();

    $this->actingAs($this->user)->get('/account')->assertOk()
        ->assertDontSee(__('account.summary.open_draft', ['opening' => 'فرصة سُحبت']))
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/account/requests')->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/account/programs')->assertHeader('Cache-Control', 'no-store, private');
    // ومسابقاتي مثلها بعد التكامل (مراجعة الدورة ٨، ٥.٧)
    $this->get('/account/competitions')->assertHeader('Cache-Control', 'no-store, private');
});

it('leaves out deleted programs and drafts of unpublished openings', function () {
    $gone = Program::factory()->create(['title' => 'برنامج محذوف']);
    ProgramRegistration::factory()->for($gone)->for($this->user)->create();
    $gone->delete();
    ServiceRequest::factory()->draft()->for($this->user)->for(Opening::factory()->unpublished()->create(['title' => 'فرصة غير منشورة']))->create();

    $response = $this->actingAs($this->user)->get('/account')->assertOk()
        ->assertDontSee('برنامج محذوف')
        ->assertDontSee(__('account.summary.open_draft', ['opening' => 'فرصة غير منشورة']));

    expect($response->viewData('summary')['upcoming_count'])->toBe(0);
});

it('orders the cards as in the reference (programs, my info, requests, then my competitions) and the sidebar as home, my info, programs, requests, my competitions', function () {
    // «مسابقاتي» بعد عناصر الصورة 04، بقرار المشرف في التكامل (القضية #6)
    $this->actingAs($this->user)->get('/account')->assertOk()
        ->assertSeeInOrder([__('programs.nav.programs_description'), __('account.nav.profile_description'), __('services.nav.requests_description'), __('competitions.account.nav_description')]);

    expect(AccountNavigation::for($this->user)->pluck('route')->all())
        ->toBe(['account.dashboard', 'account.profile', 'account.programs.index', 'account.requests.index', 'account.competitions']);
});
