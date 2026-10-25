<?php

use App\Actions\Competitions\AssignReviewers;
use App\Enums\FormAudience;
use App\Enums\MeetingBody;
use App\Enums\RoleName;
use App\Filament\Resources\Reviews\Pages\EditReview;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\AssemblyFile;
use App\Models\AssemblyMembership;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\FormEntry;
use App\Models\JobFile;
use App\Models\JobFileDocument;
use App\Models\Meeting;
use App\Models\ProgramRegistration;
use App\Models\ServiceRequest;
use App\Models\SubmissionReview;
use App\Models\SubmissionWork;
use Filament\Facades\Filament;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
| مصفوفة الوصول (docs/security/2026-10-19-اختبار-الأمن.md، البند ب١): كل مسار GET خلف الدخول يُطلب بلا دخول فيحوّل
| إلى صفحة الدخول، وكل شاشة في اللوحة وHorizon تُطلب بحساب مستفيد فترد 403، وكل سجل لمستفيد يُطلب بحساب مستفيد آخر،
| وإجراءات Livewire في اللوحة لدور غير مخوّل وعلى سجل غيره. ولكل رفض شاهد إيجابي: صاحب الحق يصل.
| والمصفوفة الكاملة لكل دور على كل مسار في feature/auth-hardening (قرار صفوان، 20 أكتوبر).
*/

/**
 * مسارات GET في الموقع واللوحة، ومعاملاتها بقيم لا تخص أحداً.
 *
 * @return list<string>
 */
function matrixGetUris(callable $filter): array
{
    return collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => in_array('GET', $route->methods(), true) && $filter($route))
        ->map(fn (Route $route) => '/'.ltrim((string) preg_replace_callback('/\{([^}?]+)\??\}/', fn (array $m) => str_contains($m[1], 'uuid') || in_array($m[1], ['serviceRequest', 'registration', 'entry', 'payment', 'round', 'submission'], true) ? (string) Str::uuid() : '1', $route->uri()), '/'))
        ->unique()
        ->values()
        ->all();
}

function matrixNeedsSignIn(Route $route): bool
{
    $middleware = implode(',', $route->gatherMiddleware());

    return str_starts_with($route->uri(), 'admin') && ! in_array($route->uri(), ['admin/login', 'admin/password-reset/request'], true)
        || str_contains($middleware, 'auth') && ! str_contains($middleware, 'auth.basic')
        || str_contains($middleware, 'Authenticate');
}

it('sends a guest to the sign-in page from every GET route behind sign-in', function () {
    $uris = matrixGetUris(fn (Route $route) => matrixNeedsSignIn($route) && ! str_starts_with($route->uri(), 'horizon'));
    expect(count($uris))->toBeGreaterThan(150);

    // لا 404 ولا 200 ولا تحويل إلى غير الدخول: الرفض من الدخول نفسه، لا من غياب السجل
    $wrong = collect($uris)->mapWithKeys(function (string $uri) {
        app('auth')->forgetGuards();
        $response = $this->get($uri);
        $toLogin = $response->isRedirect() && parse_url((string) $response->headers->get('Location'), PHP_URL_PATH) === '/login';

        return [$uri => $toLogin || in_array($response->status(), [401, 403], true) ? null : $response->status()];
    })->filter()->all();

    expect($wrong)->toBe([]);
});

it('answers 403 to an ordinary member on every admin panel screen and Horizon', function () {
    $member = userWithRole(RoleName::Member);
    $uris = matrixGetUris(fn (Route $route) => str_starts_with($route->uri(), 'admin') || str_starts_with($route->uri(), 'horizon'));
    expect(count($uris))->toBeGreaterThan(120);

    // و404 مقبول وحده لملف سجل بمعرّف لا وجود له (admin-files)، لا لشاشة
    $wrong = collect($uris)->mapWithKeys(function (string $uri) use ($member) {
        $status = $this->actingAs($member)->get($uri)->status();

        return [$uri => $status === 403 || ($status === 404 && str_starts_with($uri, '/admin-files/')) ? null : $status];
    })->filter()->all();

    expect($wrong)->toBe([]);
});

// لكل دور، لا للمستفيد وحده (مراجعة المشرف على #64): طلب المستفيد وملفه وشهادته ونموذجه وعمله في المسابقة لا تُفتح ولا تُعدَّل
// من مسارات حسابه لأحد غيره، ولو كان مشرفاً عاماً، فمكان الإدارة اللوحة بصلاحياتها وسجلها
it('never opens the records of one member to another role, by uuid or by signed link, and opens each to its owner', function (RoleName $role) {
    $victim = userWithRole(RoleName::Member);
    $attacker = userWithRole($role);

    $request = ServiceRequest::factory()->create(['user_id' => $victim->id, 'files' => ['cv' => ['path' => storedPrivateFile('service-requests'), 'name' => 'cv.pdf', 'size' => 100]]]);
    $registration = ProgramRegistration::factory()->create(['user_id' => $victim->id, 'attended_at' => now(), 'certificate_path' => storedPrivateFile('certificates')]);
    $entry = FormEntry::factory()->approved()->create(['person_id' => $victim->id]);
    $entry->load('version.template')->version->template->forceFill(['audience' => FormAudience::Participants])->save();
    $submission = CompetitionSubmission::factory()->create(['user_id' => $victim->id]);
    $work = SubmissionWork::query()->forceCreate(['submission_id' => $submission->id, 'uuid' => (string) Str::uuid(), 'type' => 'pdf', 'path' => storedPrivateFile('competition-works'), 'mime' => 'application/pdf', 'size' => 100]);

    $reads = [
        route('account.requests.show', $request),
        route('account.requests.file', [$request, 'cv']),
        route('account.forms.show', $entry),
        route('account.forms.pdf', $entry),
        route('account.programs.certificate', $registration),
        URL::temporarySignedRoute('competitions.works.show', now()->addMinutes(5), ['work' => $work]),
    ];
    foreach ($reads as $uri) {
        expect($this->actingAs($attacker)->get($uri)->getStatusCode())->toBeIn([403, 404], $uri);
        // والمالك يصل إلى كل منها، فالرفض للغريب من السياسة لا من خلل في البيانات أو التوقيع
        expect($this->actingAs($victim)->get($uri)->getStatusCode())->toBe(200, $uri);
    }

    expect($this->actingAs($attacker)->put(route('account.requests.update', $request), ['intent' => 'save', 'answers' => ['about' => 'x']])->status())->toBeIn([403, 404]);
    expect($this->actingAs($attacker)->post(route('account.programs.cancel', $registration))->status())->toBeIn([403, 404]);
    expect($this->actingAs($attacker)->delete(URL::temporarySignedRoute('competitions.works.destroy', now()->addMinutes(5), ['work' => $work]))->status())->toBeIn([403, 404]);

    expect($request->fresh()->answers)->not->toBe(['about' => 'x'])
        ->and($registration->fresh()->status)->toBe($registration->status)
        ->and(SubmissionWork::query()->whereKey($work->id)->exists())->toBeTrue();
})->with(fn () => array_map(fn (RoleName $role) => [$role], RoleName::cases()));

// عضو في الجمعية العمومية يجتاز بوابة المنطقة، فيُختبر فحص السجل نفسه: اجتماع المجلس وملفه لا يُفتحان له ولو بتوقيع
// صالح، واجتماع الجمعية وملفها يُفتحان. وملف وظيفة لموظف بلا صلة بها، وصاحب الوظيفة يصل إليه
it('keeps board meetings and files from an assembly member, and job files from staff outside the job', function () {
    Storage::fake('local');
    $member = AssemblyMembership::factory()->create()->user;
    $boardMeeting = Meeting::factory()->withApprovedMinutes()->create(['body' => MeetingBody::Board]);
    $assemblyMeeting = Meeting::factory()->withApprovedMinutes()->create(['body' => MeetingBody::GeneralAssembly]);
    $boardFile = AssemblyFile::factory()->forBoard()->create();
    $assemblyFile = AssemblyFile::factory()->create();
    foreach ([$boardFile, $assemblyFile] as $file) {
        Storage::disk('local')->put((string) $file->file, pdfContents());
    }

    $signed = fn (string $name, array $parameters) => URL::temporarySignedRoute($name, now()->addMinutes(5), $parameters);

    foreach ([route('assembly.meetings.show', $boardMeeting), $signed('assembly.meetings.minutes', ['meeting' => $boardMeeting]), $signed('assembly.files.download', ['file' => $boardFile])] as $uri) {
        expect($this->actingAs($member)->get($uri)->status())->toBeIn([403, 404], $uri);
    }
    $this->actingAs($member)->get(route('assembly.meetings.show', $assemblyMeeting))->assertOk();
    $this->actingAs($member)->get($signed('assembly.files.download', ['file' => $assemblyFile]))->assertOk();

    $document = JobFileDocument::factory()->create(['job_file_id' => JobFile::factory()->create(['responsible_role' => RoleName::FinancialManager])->id]);
    Storage::disk(JobFileDocument::disk())->put($document->path, pdfContents());
    $link = $signed('job-files.documents.download', ['document' => $document]);

    expect($this->actingAs(userWithRole(RoleName::Accountant))->get($link)->getStatusCode())->toBeIn([403, 404]);
    $this->actingAs(userWithRole(RoleName::FinancialManager))->get($link)->assertOk();
});

// إجراءات Livewire في اللوحة: الشاشة لا تُفتح لغير المخوّل، فلا يصل إلى «حفظ» ولا «حذف»، والسجل كما هو.
// ومنها IDOR كتابة لدورين: المدير التنفيذي على حساب المشرف العام، والمحكّم على تحكيم غيره
it('refuses panel Livewire actions to a role without the permission and on a record that is not theirs', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $superAdmin = userWithRole(RoleName::SuperAdmin, ['name' => 'المشرف الأصلي']);
    $director = userWithRole(RoleName::ExecutiveDirector);
    $staff = userWithRole(RoleName::Accountant, ['name' => 'موظف']);

    // المدير التنفيذي: حساب المشرف العام خارج نطاقه (404)، ولا يُبدَّل سجل نموذج يملكه إلى حسابه (الخاصية مقفلة)
    $this->actingAs($director);
    Livewire::test(EditUser::class, ['record' => $superAdmin->getRouteKey()])->assertNotFound();
    expect(fn () => Livewire::test(EditUser::class, ['record' => $staff->getRouteKey()])->set('record', $superAdmin->getKey())->fillForm(['name' => 'مخترَق'])->call('save'))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    // السكرتير بلا صلاحية المستخدمين (403)، والمشرف العام يفتحه (شاهد إيجابي)
    $this->actingAs(userWithRole(RoleName::Secretary));
    Livewire::test(EditUser::class, ['record' => $superAdmin->getRouteKey()])->assertForbidden();
    $this->actingAs(userWithRole(RoleName::SuperAdmin));
    Livewire::test(EditUser::class, ['record' => $superAdmin->getRouteKey()])->assertOk();
    expect($superAdmin->fresh()->name)->toBe('المشرف الأصلي');

    $competition = Competition::factory()->withQuestions()->create();
    [$first, $second] = $competition->criteria->all();
    $manager = userWithRole(RoleName::ProgramManager);
    $reviewerA = userWithRole(RoleName::Reviewer);
    $reviewerB = userWithRole(RoleName::Reviewer);
    $competition->reviewers()->attach([$reviewerA->id, $reviewerB->id]);
    $entry = CompetitionSubmission::factory()->submitted()->create(['competition_id' => $competition->id]);
    app(AssignReviewers::class)->handle($manager, $competition, [$entry->id], [$reviewerB->id]);
    $review = SubmissionReview::query()->sole();

    $this->actingAs($reviewerA);
    Livewire::test(EditReview::class, ['record' => $review->getRouteKey()])->assertNotFound();
    expect($review->fresh()->completed_at)->toBeNull();

    // وصاحب التحكيم يفتحه، فالرفض لغيره من السياسة
    $this->actingAs($reviewerB);
    Livewire::test(EditReview::class, ['record' => $review->getRouteKey()])->assertOk();
});

// والطلب الخام إلى نقطة Livewire بلقطة مزوّرة (مكوّن تعديل المستخدم في اللوحة، وتوقيع غير صحيح) مرفوض قبل أي إجراء.
// برأس X-Livewire كعميل Livewire نفسه، فيبلغ فحص التوقيع لا فحص الرأس (RequireLivewireHeaders)
it('refuses a forged Livewire update request from a member', function () {
    $target = userWithRole(RoleName::Accountant, ['name' => 'موظف']);
    $status = $this->actingAs(userWithRole(RoleName::Member))
        ->withHeader('X-Livewire', 'true')
        ->postJson(route('default-livewire.update'), ['components' => [[
            'snapshot' => json_encode(['data' => ['record' => $target->getKey()], 'memo' => ['name' => 'filament.admin.resources.users.pages.edit-user', 'id' => 'x', 'path' => 'admin/users/'.$target->getKey().'/edit', 'method' => 'GET'], 'checksum' => 'forged']),
            'updates' => ['data.name' => 'مخترَق'],
            'calls' => [['method' => 'save', 'params' => []]],
        ]]])->getStatusCode();

    // 419 بالضبط من فحص سلامة اللقطة (LivewireReleaseTokenMismatchException)، كما في تقرير الأمن
    expect($status)->toBe(419)
        ->and($target->fresh()->name)->toBe('موظف');
});
