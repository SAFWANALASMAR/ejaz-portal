<?php

namespace App\Filament\Resources\Reviews\Pages;

use App\Actions\Competitions\ScoreSubmission;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Models\SubmissionReview;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * إدخال المحكّم درجاته. والصفحة تفتح لمن له تعديل التحكيم (المسند إليه قبل الاعتماد)، والحفظ عبر
 * ScoreSubmission وأخطاؤه تحت حقولها.
 */
class EditReview extends EditRecord
{
    protected static string $resource = ReviewResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var SubmissionReview $review */
        $review = $this->getRecord();

        return ['scores' => (array) $review->scores, 'complete' => $review->completed_at !== null];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var SubmissionReview $record */
        /** @var User $reviewer */
        $reviewer = auth()->user();

        try {
            return app(ScoreSubmission::class)->handle($reviewer, $record, (array) ($data['scores'] ?? []), (bool) ($data['complete'] ?? false));
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ['data.scores.'.$key => $messages])->all(),
            );
        }
    }
}
