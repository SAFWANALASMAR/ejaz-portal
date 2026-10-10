<?php

namespace App\Actions\Competitions;

use App\Enums\WorkType;
use App\Models\CompetitionSubmission;
use App\Models\SubmissionWork;
use App\Models\User;
use App\Rules\RealPdf;
use App\Rules\SafeUrl;
use App\Support\ImageSanitizer;
use App\Support\SubmissionLock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * رفع عمل إلى المشاركة: «رفع الأعمال (صور – PDF – روابط)» بالأنواع المسموحة في المسابقة وحدها.
 * الصورة يُعاد ترميزها (تُزال بياناتها الوصفية)، والـ PDF حقيقي بلا محتوى نشط، والرابط https وحده.
 * والملفات في القرص الخاص بأسماء عشوائية. والإضافة بقفل صف المشاركة مسودةً، فلا تتجاوز السقف ولا تصل بعد الإرسال.
 */
class AddSubmissionWork
{
    /**
     * @param  array<string, mixed>  $data  {type, file?: UploadedFile, url?: string}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, CompetitionSubmission $submission, array $data): SubmissionWork
    {
        if (! $user->can('update', $submission)) {
            throw new AuthorizationException;
        }

        $competition = $submission->competition;

        if (! $competition->isOpen()) {
            throw new AuthorizationException(__('competitions.entry.closed'));
        }

        $allowed = array_map(fn (WorkType $type): string => $type->value, $competition->workTypeList());
        $type = WorkType::tryFrom(is_string($data['type'] ?? null) ? $data['type'] : '');

        $validated = Validator::make($data, [
            'type' => ['required', Rule::in($allowed)],
            'file' => match ($type) {
                // الأبعاد من رأس الملف قبل فكّه: صورة مضغوطة بأبعاد ضخمة تستنزف ذاكرة GD عند إعادة الترميز
                WorkType::Image => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.config('competitions.works.image_max_kb'), 'dimensions:max_width=8000,max_height=8000'],
                WorkType::Pdf => ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('competitions.works.pdf_max_kb'), new RealPdf],
                default => ['prohibited'],
            },
            'url' => $type === WorkType::Link ? ['required', 'string', 'max:2048', new SafeUrl(allowInternal: false)] : ['prohibited'],
        ], attributes: __('competitions.works.attributes'))->validate();

        // فحص أولي رخيص قبل تخزين الملف، ويُعاد تحت القفل
        if ($submission->works()->count() >= (int) config('competitions.works.max_items')) {
            throw ValidationException::withMessages(['type' => __('competitions.works.too_many', ['max' => config('competitions.works.max_items')])]);
        }

        /** @var WorkType $type */
        $path = null;
        $file = $validated['file'] ?? null;

        if ($file instanceof UploadedFile) {
            $path = $this->store($file, $type);
        }

        try {
            return DB::transaction(function () use ($submission, $type, $path, $validated): SubmissionWork {
                $locked = SubmissionLock::draft($submission->id);

                if ($locked->works()->count() >= (int) config('competitions.works.max_items')) {
                    throw ValidationException::withMessages(['type' => __('competitions.works.too_many', ['max' => config('competitions.works.max_items')])]);
                }

                $work = new SubmissionWork;
                $work->submission_id = $locked->id;
                $work->type = $type;

                if ($path !== null) {
                    $storage = Storage::disk(SubmissionWork::disk());
                    $work->path = $path;
                    $work->mime = (string) $storage->mimeType($path);
                    $work->size = (int) $storage->size($path);
                } else {
                    $work->url = (string) $validated['url'];
                }

                $work->save();

                return $work;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk(SubmissionWork::disk())->delete($path);
            }

            throw $exception;
        }
    }

    /**
     * @throws ValidationException
     */
    private function store(UploadedFile $file, WorkType $type): string
    {
        $disk = SubmissionWork::disk();
        $path = $file->store(SubmissionWork::DIRECTORY, $disk);

        if ($path === false) {
            throw ValidationException::withMessages(['file' => __('competitions.works.upload_failed')]);
        }

        if ($type === WorkType::Image) {
            try {
                ImageSanitizer::strip($disk, $path);
            } catch (Throwable) {
                Storage::disk($disk)->delete($path);

                throw ValidationException::withMessages(['file' => __('competitions.works.bad_image')]);
            }
        }

        return $path;
    }
}
