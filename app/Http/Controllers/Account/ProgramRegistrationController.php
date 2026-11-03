<?php

namespace App\Http\Controllers\Account;

use App\Actions\Programs\CancelProgramRegistration;
use App\Actions\Programs\CompleteLibraryProgram;
use App\Actions\Programs\RegisterForProgram;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Programs\RegisterForProgramRequest;
use App\Jobs\SyncAttendanceCertificate;
use App\Models\LibraryProgress;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * التسجيل في برنامج، و«برامجي»: التسجيلات بحالاتها وإلغاؤها. كل تسجيل لصاحبه فقط.
 */
class ProgramRegistrationController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        // الجارية بتقدّمها، و«برامج أتممتها» وحدها تحتها (برامج المكتبة — docs/decisions/2026-11-02-reading-program.md)
        $registrations = ProgramRegistration::query()
            ->whereBelongsTo($user)
            ->whereNull('completed_at')
            ->with(['program.type', 'program.libraryItem'])
            ->latest('id')
            ->paginate(15);

        // لا صفحة بعد الأخيرة، فلا تُعرض قائمة فارغة برقمها في العنوان (كبقية القوائم المرقّمة)
        abort_if($registrations->currentPage() > $registrations->lastPage(), 404);

        $completed = ProgramRegistration::query()
            ->whereBelongsTo($user)
            ->whereNotNull('completed_at')
            ->with('program.type')
            ->latest('completed_at')
            ->limit(50)
            ->get();

        return view('account.programs.index', ['registrations' => $registrations, 'completed' => $completed, 'progress' => LibraryProgress::forRegistrations($user, $registrations->getCollection())]);
    }

    public function create(Request $request, string $slug): View|RedirectResponse
    {
        $program = Program::query()->published()->where('slug', $slug)->firstOrFail();

        $registered = ProgramRegistration::query()
            ->where('program_id', $program->id)
            ->whereBelongsTo($request->user())
            ->where('status', '!=', RegistrationStatus::Cancelled->value)
            ->exists();

        if ($registered || $program->status() !== ProgramStatus::Open) {
            return redirect()->route('programs.show', $program->slug);
        }

        return view('programs.register', ['program' => $program]);
    }

    public function store(RegisterForProgramRequest $request, RegisterForProgram $register): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array<string, mixed> $answers */
        $answers = $request->validated('answers') ?? [];
        try {
            $registration = $register->handle($user, $request->program(), $answers);
        } catch (ValidationException $exception) {
            // صفحة التسجيل تُغلق إذا امتلأ البرنامج، فيظهر السبب في صفحته
            if (isset($exception->errors()['registration'])) {
                return redirect()->route('programs.show', $request->program()->slug)->with('error', $exception->errors()['registration'][0]);
            }

            throw $exception;
        }

        return redirect()->route('programs.show', $request->program()->slug)
            ->with('status', __($registration->status === RegistrationStatus::Confirmed ? 'programs.registered' : 'programs.waitlisted'));
    }

    /**
     * شهادة الحضور لصاحبها، تنزيلاً. وتسجيل غيره كأنه غير موجود.
     */
    public function certificate(Request $request, ProgramRegistration $registration): StreamedResponse
    {
        // تسجيل غيره أو بلا شهادة كأنه غير موجود
        abort_unless($request->user()?->can('download', $registration), 404);

        return Storage::disk(SyncAttendanceCertificate::disk())
            ->download((string) $registration->certificate_path, 'certificate-'.$registration->program->slug.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    /**
     * «أتممت» في برنامج مكتبة (CompleteLibraryProgram): لصاحب التسجيل وحده، وبعد بلوغ النهاية بقواعد الخادم.
     */
    public function complete(Request $request, ProgramRegistration $registration, CompleteLibraryProgram $complete): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($registration->user_id === $user->id, 404);

        try {
            $complete->handle($user, $registration);
        } catch (ValidationException $exception) {
            return redirect()->route('programs.show', $registration->program->slug)->with('error', $exception->errors()['complete'][0] ?? __('programs.library.not_yet'));
        }

        return redirect()->route('programs.show', $registration->program->slug)->with('status', __('programs.library.completed'));
    }

    public function cancel(Request $request, ProgramRegistration $registration, CancelProgramRegistration $cancel): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // تسجيل غيره كأنه غير موجود
        abort_unless($registration->user_id === $user->id, 404);

        $cancel->handle($user, $registration);

        return redirect()->route('account.programs.index')->with('status', __('programs.cancelled'));
    }
}
