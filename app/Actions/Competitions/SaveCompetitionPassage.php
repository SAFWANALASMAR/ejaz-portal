<?php

namespace App\Actions\Competitions;

use App\Enums\PassageKind;
use App\Models\CompetitionCategory;
use App\Models\CompetitionPassage;
use App\Models\CompetitionRound;
use App\Models\User;
use App\Rules\VideoUrl;
use App\Support\CompetitionAccess;
use App\Support\RoundQuestions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ نص مقروء أو مقطع يوتيوب في مرحلة من مسابقة الطلاب: النص فقرة تُعرض فوق أسئلتها، والمقطع رابط يوتيوب
 * يُضمَّن من youtube-nocookie (لا ملف مرفوع)، ومعه شرح اختياري. ومقفل مع البنك بعد أول محاولة.
 */
class SaveCompetitionPassage
{
    /**
     * @param  array<string, mixed>  $data  {kind, title?, body?, video_url?, category_id?}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, CompetitionRound $round, ?CompetitionPassage $passage, array $data): CompetitionPassage
    {
        $competition = $round->competition;

        if (! CompetitionAccess::allows($actor, 'competitions.update') || ! $competition->student_mode || ($passage !== null && $passage->round_id !== $round->id)) {
            throw new AuthorizationException;
        }

        $kind = PassageKind::tryFrom((string) ($data['kind'] ?? ''));

        $validated = Validator::make($data, [
            'kind' => ['required', Rule::enum(PassageKind::class)],
            'title' => ['nullable', 'string', 'max:200'],
            'body' => [$kind === PassageKind::Text ? 'required' : 'nullable', 'string', 'max:10000'],
            'video_url' => [$kind === PassageKind::Video ? 'required' : 'nullable', 'string', 'max:255', new VideoUrl(youtubeOnly: true)],
            'category_id' => ['nullable', 'integer', Rule::exists(CompetitionCategory::class, 'id')],
        ], attributes: __('competitions.student.passage_attributes'))->validate();

        $saved = DB::transaction(function () use ($round, $passage, $validated, $kind): CompetitionPassage {
            $locked = CompetitionRound::query()->whereKey($round->id)->lockForUpdate()->firstOrFail();

            if ($locked->locked()) {
                throw ValidationException::withMessages(['body' => __('competitions.student.bank_locked')]);
            }

            $passage ??= new CompetitionPassage;
            $passage->competition_id = $round->competition_id;
            $passage->fill([
                'round_id' => $round->id,
                'kind' => $kind,
                'title' => filled($validated['title'] ?? null) ? trim((string) $validated['title']) : null,
                'body' => filled($validated['body'] ?? null) ? trim((string) $validated['body']) : null,
                'video_url' => $kind === PassageKind::Video ? trim((string) $validated['video_url']) : null,
                'category_id' => filled($validated['category_id'] ?? null) ? (int) $validated['category_id'] : null,
            ])->save();

            // أسئلته تتبع فئته، فلا يتفرق النص عن أسئلته بين الفئات
            $passage->questions()->update(['category_id' => $passage->category_id]);

            RoundQuestions::forget($round->id);

            return $passage;
        });

        DB::afterCommit(fn () => RoundQuestions::forget($round->id));

        return $saved;
    }
}
