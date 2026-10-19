<?php

use App\Enums\FormAudience;
use App\Enums\RoleName;
use App\Models\AssemblyFile;
use App\Models\CompetitionSubmission;
use App\Models\FormEntry;
use App\Models\JobFileDocument;
use App\Models\Meeting;
use App\Models\ProgramRegistration;
use App\Models\ServiceRequest;
use App\Models\SubmissionWork;
use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/*
| مصفوفة الوصول (docs/security/2026-10-19-اختبار-الأمن.md، البند ب١): كل مسار خلف الدخول يُطلب بلا دخول،
| وكل مسار في اللوحة يُطلب بحساب مستفيد، وكل سجل لمستفيد يُطلب بحساب مستفيد آخر. لا 200 ولا 500 في أي منها.
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
        ->map(fn (Route $route) => '/'.ltrim((string) preg_replace_callback('/\{([^}?]+)\??\}/', fn (array $m) => str_contains($m[1], 'uuid') || in_array($m[1], ['serviceRequest', 'registration', 'entry', 'payment'], true) ? (string) Str::uuid() : '1', $route->uri()), '/'))
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

it('refuses a guest on every route behind sign-in, without a server error', function () {
    $uris = matrixGetUris(fn (Route $route) => matrixNeedsSignIn($route) && ! str_starts_with($route->uri(), 'horizon'));
    expect(count($uris))->toBeGreaterThan(50);

    $open = collect($uris)->filter(fn (string $uri) => in_array($this->get($uri)->status(), [200, 500], true))->values()->all();

    expect($open)->toBe([]);
});

it('keeps an ordinary member out of every admin panel screen and Horizon', function () {
    $member = User::factory()->create();
    $member->assignRole(RoleName::Member->value);
    $uris = matrixGetUris(fn (Route $route) => str_starts_with($route->uri(), 'admin') || str_starts_with($route->uri(), 'horizon'));
    expect(count($uris))->toBeGreaterThan(80);

    $open = collect($uris)->filter(fn (string $uri) => in_array($this->actingAs($member)->get($uri)->status(), [200, 500], true))->values()->all();

    expect($open)->toBe([]);
});

it('never opens the records of one member to another, by uuid or by signed link', function () {
    $victim = userWithRole(RoleName::Member);
    $attacker = userWithRole(RoleName::Member);

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
        route('documents.entries.print', $entry),
        route('documents.entries.pdf', $entry),
    ];
    foreach ($reads as $uri) {
        expect($this->actingAs($attacker)->get($uri)->status())->toBeIn([403, 404], $uri);
    }
    // والمالك يصل إليها، فالرفض للغريب لا لخلل في البيانات
    $this->actingAs($victim)->get(route('account.requests.show', $request))->assertOk();
    $this->actingAs($victim)->get(route('account.requests.file', [$request, 'cv']))->assertOk();
    $this->actingAs($victim)->get(route('account.forms.show', $entry))->assertOk();

    expect($this->actingAs($attacker)->put(route('account.requests.update', $request), ['answers' => ['about' => 'x']])->status())->toBeIn([403, 404]);
    expect($this->actingAs($attacker)->post(route('account.programs.cancel', $registration))->status())->toBeIn([403, 404]);
    expect($this->actingAs($attacker)->delete(URL::temporarySignedRoute('competitions.works.destroy', now()->addMinutes(5), ['work' => $work]))->status())->toBeIn([403, 404]);

    expect($request->fresh()->answers)->not->toBe(['about' => 'x'])
        ->and($registration->fresh()->status)->toBe($registration->status)
        ->and(SubmissionWork::query()->whereKey($work->id)->exists())->toBeTrue();
});

it('keeps assembly files, meetings and job files from a member outside them, even with a valid signature', function () {
    $outsider = userWithRole(RoleName::Member);
    $file = AssemblyFile::factory()->create();
    $meeting = Meeting::factory()->create();
    $document = JobFileDocument::factory()->create();

    foreach ([
        URL::temporarySignedRoute('assembly.files.download', now()->addMinutes(5), ['file' => $file]),
        route('assembly.meetings.show', $meeting),
        URL::temporarySignedRoute('assembly.meetings.minutes', now()->addMinutes(5), ['meeting' => $meeting]),
        URL::temporarySignedRoute('job-files.documents.download', now()->addMinutes(5), ['document' => $document]),
    ] as $uri) {
        expect($this->actingAs($outsider)->get($uri)->status())->toBeIn([403, 404], $uri);
    }
});
