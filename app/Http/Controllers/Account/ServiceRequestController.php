<?php

namespace App\Http\Controllers\Account;

use App\Actions\Services\SaveServiceRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Services\SaveServiceRequestRequest;
use App\Models\Opening;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «طلباتي»: التقديم على فرصة، وحفظ الطلب مسودة أو إرساله، ومتابعته وتنزيل مرفقاته.
 * كل طلب لصاحبه فقط (ServiceRequestPolicy)، ويُطلب بمعرّفه uuid.
 */
class ServiceRequestController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $requests = ServiceRequest::query()
            ->whereBelongsTo($user)
            ->with('opening')
            ->latest('id')
            ->paginate(15);

        return view('account.requests.index', ['requests' => $requests]);
    }

    public function create(Request $request, string $slug): View|RedirectResponse
    {
        $opening = Opening::query()->published()->where('slug', $slug)->firstOrFail();

        $existing = ServiceRequest::query()->where('opening_id', $opening->id)->whereBelongsTo($request->user())->first();

        if ($existing !== null) {
            return redirect()->route('account.requests.show', $existing);
        }

        abort_unless($opening->acceptsApplications(), 404);

        return view('account.requests.form', ['opening' => $opening, 'serviceRequest' => null]);
    }

    public function store(SaveServiceRequestRequest $request, SaveServiceRequest $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $saved = $action->handle($user, $request->opening(), null, self::answers($request), self::files($request), $request->submitting());

        return redirect()->route('account.requests.show', $saved)
            ->with('status', __($request->submitting() ? 'services.submitted' : 'services.saved'));
    }

    public function show(Request $request, ServiceRequest $serviceRequest): View
    {
        $this->authorizeOwner($request, $serviceRequest);

        $serviceRequest->load(['opening', 'events']);

        return view('account.requests.show', ['serviceRequest' => $serviceRequest]);
    }

    public function update(SaveServiceRequestRequest $request, ServiceRequest $serviceRequest, SaveServiceRequest $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->handle($user, $serviceRequest->opening, $serviceRequest, self::answers($request), self::files($request), $request->submitting());

        return redirect()->route('account.requests.show', $serviceRequest)
            ->with('status', __($request->submitting() ? 'services.submitted' : 'services.saved'));
    }

    /**
     * المرفق لصاحب الطلب، تنزيلاً لا عرضاً مدمجاً.
     */
    public function file(Request $request, ServiceRequest $serviceRequest, string $key): StreamedResponse
    {
        $this->authorizeOwner($request, $serviceRequest);

        $file = $serviceRequest->files[$key] ?? abort(404);

        return Storage::disk(ServiceRequest::disk())->download($file['path'], $key.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    /**
     * صفحات «طلباتي» لصاحب الطلب وحده، ولو ملك غيرُه الاطلاع عليه من اللوحة. والطلب لغيره
     * يُعامل كأنه غير موجود، فلا يُكشف وجوده.
     */
    private function authorizeOwner(Request $request, ServiceRequest $serviceRequest): void
    {
        abort_unless($serviceRequest->user_id === $request->user()?->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private static function answers(SaveServiceRequestRequest $request): array
    {
        /** @var array<string, mixed> $answers */
        $answers = $request->validated('answers') ?? [];

        return $answers;
    }

    /**
     * @return array<string, UploadedFile>
     */
    private static function files(SaveServiceRequestRequest $request): array
    {
        /** @var array<string, UploadedFile> $files */
        $files = array_filter((array) $request->file('files', []), fn (mixed $file): bool => $file instanceof UploadedFile);

        return $files;
    }
}
