<?php

namespace App\Actions\Services;

use App\Enums\FormFieldType;
use App\Enums\ServiceRequestStatus;
use App\Models\Opening;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\ServiceRequestStatusChanged;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * حفظ طلب المتقدم مسودة أو إرساله (requirements/02).
 *
 * - الطلب الجديد على فرصة تقبل التقديم، وطلب واحد لكل مستخدم في الفرصة (فهرس فريد أيضاً).
 * - التعديل لصاحب الطلب ما دام مسودة أو بحاجة لاستكمال (ServiceRequestPolicy::edit).
 * - الإجابات لحقول الفرصة فقط، والمرفقات في القرص الخاص بأسماء عشوائية، واستبدالها يحذف السابق.
 * - الإرسال يشترط اكتمال الحقول الإلزامية، فيصير الطلب «مُستلماً» ويُسجَّل ويُشعَر صاحبه.
 *
 * المدخلات فُحصت أنواعها قبل الإجراء (OpeningFormRules)، والإجراء يعيد فحص ما يحميه هو.
 */
class SaveServiceRequest
{
    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, UploadedFile>  $files
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, Opening $opening, ?ServiceRequest $request, array $answers, array $files, bool $submit): ServiceRequest
    {
        $request = $request === null ? $this->start($user, $opening) : $this->resume($user, $request);
        $opening = $request->opening;

        $fields = collect($opening->fields())->keyBy('key');
        $request->answers = [
            ...($request->answers ?? []),
            ...collect($answers)->only($fields->reject(fn (array $field): bool => $field['type'] === FormFieldType::Pdf)->keys())->all(),
        ];

        $stored = [];
        $replaced = [];
        $current = $request->files ?? [];

        foreach ($files as $key => $file) {
            if (($fields->get($key)['type'] ?? null) !== FormFieldType::Pdf) {
                continue;
            }

            $path = $file->store(ServiceRequest::FILE_DIRECTORY, ServiceRequest::disk());

            if ($path === false) {
                throw ValidationException::withMessages(['files.'.$key => __('validation.uploaded')]);
            }

            $stored[] = $path;

            if (isset($current[$key])) {
                $replaced[] = $current[$key]['path'];
            }

            $current[$key] = ['path' => $path, 'name' => Str::limit($file->getClientOriginalName(), 150, ''), 'size' => (int) $file->getSize()];
        }

        $request->files = $current === [] ? null : $current;

        try {
            if ($submit) {
                $this->ensureComplete($request, $fields->all());
            }

            DB::transaction(function () use ($request, $user, $submit): void {
                // قفل الطلب وإعادة فحص حالته، فلا يمحو حفظُ المتقدم قراراً اتخذه موظف في الأثناء
                if ($request->exists) {
                    $current = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->value('status');

                    if ($current !== $request->getOriginal('status')) {
                        throw new AuthorizationException;
                    }
                }

                $from = $request->exists ? $request->status : null;

                if ($submit) {
                    $request->status = ServiceRequestStatus::Received;
                    $request->submitted_at ??= now();
                }

                $request->save();

                if ($submit) {
                    $event = $request->events()->create([
                        'actor_id' => $user->id,
                        'from_status' => $from ?? ServiceRequestStatus::Draft,
                        'to_status' => ServiceRequestStatus::Received,
                    ]);

                    $user->notify(new ServiceRequestStatusChanged($request, $event));
                }
            });
        } catch (UniqueConstraintViolationException) {
            Storage::disk(ServiceRequest::disk())->delete($stored);

            throw ValidationException::withMessages(['answers' => __('services.already_applied')]);
        } catch (\Throwable $exception) {
            Storage::disk(ServiceRequest::disk())->delete($stored);

            throw $exception;
        }

        Storage::disk(ServiceRequest::disk())->delete($replaced);

        return $request;
    }

    /**
     * @throws ValidationException
     */
    private function start(User $user, Opening $opening): ServiceRequest
    {
        if (! $opening->acceptsApplications()) {
            throw ValidationException::withMessages(['answers' => __('services.closed')]);
        }

        if (ServiceRequest::where('opening_id', $opening->id)->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['answers' => __('services.already_applied')]);
        }

        $request = new ServiceRequest(['status' => ServiceRequestStatus::Draft]);
        $request->opening_id = $opening->id;
        $request->user_id = $user->id;
        $request->setRelation('opening', $opening);

        return $request;
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    private function resume(User $user, ServiceRequest $request): ServiceRequest
    {
        if (! $user->can('edit', $request)) {
            throw new AuthorizationException;
        }

        // المسودة تُغلق بإغلاق الفرصة. وما طُلب استكماله يُستكمل ولو أُغلقت
        if ($request->status === ServiceRequestStatus::Draft && ! $request->opening->acceptsApplications()) {
            throw ValidationException::withMessages(['answers' => __('services.closed')]);
        }

        return $request;
    }

    /**
     * @param  array<string, array{key: string, label: string, type: FormFieldType, required: bool, options: list<string>}>  $fields
     *
     * @throws ValidationException
     */
    private function ensureComplete(ServiceRequest $request, array $fields): void
    {
        $missing = [];

        foreach ($fields as $key => $field) {
            if (! $field['required']) {
                continue;
            }

            $present = $field['type'] === FormFieldType::Pdf ? isset($request->files[$key]) : filled($request->answers[$key] ?? null);

            if (! $present) {
                $prefix = $field['type'] === FormFieldType::Pdf ? 'files.' : 'answers.';
                $missing[$prefix.$key] = __('validation.required', ['attribute' => $field['label']]);
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }
    }
}
