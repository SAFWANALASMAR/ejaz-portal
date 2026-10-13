<?php

use App\Actions\Competitions\SaveCompetition;
use App\Enums\QuestionMedia;
use App\Enums\RoleName;
use App\Filament\Resources\Competitions\CompetitionResource;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Support\ExamQuestions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
| إدارة الاختبار الإلكتروني والأعمال في SaveCompetition (docs/plans/competition-exam.md).
*/

beforeEach(function () {
    Storage::fake('local');
    $this->manager = userWithRole(RoleName::ProgramManager);
    Storage::disk('local')->put('competition-media/intro.mp4', mp4Contents(95));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function examForm(array $overrides = []): array
{
    $options = fn (): array => [
        ['label' => 'أ', 'is_correct' => false],
        ['label' => 'ب', 'is_correct' => true],
        ['label' => 'ج', 'is_correct' => false],
        ['label' => 'د', 'is_correct' => false],
    ];

    return [
        'title' => 'اختبار تجريبي',
        'slug' => 'exam-demo',
        'categories' => [],
        'opens_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'closes_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
        'is_published' => true,
        'exam_mode' => true,
        'intro_video_path' => 'competition-media/intro.mp4',
        'questions' => [
            ['type' => 'multiple_choice', 'prompt' => 'سؤال أول', 'is_required' => true, 'points' => 1, 'options' => $options(), 'context_text' => 'نص علمي'],
            ['type' => 'multiple_choice', 'prompt' => 'سؤال ثانٍ', 'is_required' => true, 'points' => 1, 'options' => $options()],
        ],
        ...$overrides,
    ];
}

it('saves an exam with its intro video duration read from the file, and question context', function () {
    $competition = app(SaveCompetition::class)->handle($this->manager, new Competition, examForm());

    expect($competition->exam_mode)->toBeTrue()
        ->and($competition->intro_video_seconds)->toBe(95)
        ->and($competition->questions->first()->context_text)->toBe('نص علمي');

    $this->actingAs($this->manager)->get(CompetitionResource::getUrl('edit', ['record' => $competition]))->assertOk()
        ->assertSee(__('competitions.exam_admin.exam_mode'));
});

it('requires exactly four options per exam question and an intro video to publish', function (array $overrides, string $field) {
    expect(fn () => app(SaveCompetition::class)->handle($this->manager, new Competition, examForm($overrides)))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey($field));
})->with([
    'three options' => [['questions' => [['type' => 'multiple_choice', 'prompt' => 'س', 'points' => 1, 'options' => [
        ['label' => 'أ', 'is_correct' => true], ['label' => 'ب', 'is_correct' => false], ['label' => 'ج', 'is_correct' => false],
    ]]]], 'questions'],
    'a text question' => [['questions' => [['type' => 'text', 'prompt' => 'س']]], 'questions'],
    'no intro video' => [['intro_video_path' => null], 'intro_video_path'],
    'an intro outside its folder' => [['intro_video_path' => '../secret.mp4'], 'intro_video_path'],
]);

it('drops work types from an exam, since its hidden field is not sent', function () {
    $competition = app(SaveCompetition::class)->handle($this->manager, new Competition, examForm(['work_types' => ['image']]));

    expect($competition->work_types)->toBeNull()
        ->and($competition->workTypeList())->toBe([]);
});

it('refuses a media path that belongs to another competition, and keeps that file', function () {
    $first = app(SaveCompetition::class)->handle($this->manager, new Competition, examForm());

    expect(fn () => app(SaveCompetition::class)->handle($this->manager, new Competition, examForm(['slug' => 'second'])))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('intro_video_path'));

    Storage::disk('local')->assertExists('competition-media/intro.mp4');
    expect($first->fresh()->intro_video_path)->toBe('competition-media/intro.mp4');
});

it('refuses an intro video that is not a readable MP4, and question media not matching its type', function () {
    Storage::disk('local')->put('competition-media/fake.mp4', 'not a video');
    Storage::disk('local')->put('competition-media/fake.png', 'not an image');

    expect(fn () => app(SaveCompetition::class)->handle($this->manager, new Competition, examForm(['intro_video_path' => 'competition-media/fake.mp4'])))
        ->toThrow(ValidationException::class);

    $form = examForm();
    $form['questions'][0] += ['media_type' => 'image', 'media_path' => 'competition-media/fake.png'];
    expect(fn () => app(SaveCompetition::class)->handle($this->manager, new Competition, $form))->toThrow(ValidationException::class);

    $form = examForm();
    $form['questions'][0] += ['media_type' => 'audio'];
    expect(fn () => app(SaveCompetition::class)->handle($this->manager, new Competition, $form))->toThrow(ValidationException::class);

    expect(Competition::count())->toBe(0);
});

it('saves question media, and deletes media removed from the competition', function () {
    Storage::disk('local')->put('competition-media/clip.mp3', mp3Contents());
    $form = examForm();
    $form['questions'][1] += ['media_type' => 'audio', 'media_path' => 'competition-media/clip.mp3'];

    $competition = app(SaveCompetition::class)->handle($this->manager, new Competition, $form);
    expect($competition->questions[1]->media_type)->toBe(QuestionMedia::Audio);

    Storage::disk('local')->put('competition-media/intro2.mp4', mp4Contents(40));
    app(SaveCompetition::class)->handle($this->manager, $competition, examForm(['intro_video_path' => 'competition-media/intro2.mp4']));

    Storage::disk('local')->assertMissing(['competition-media/intro.mp4', 'competition-media/clip.mp3']);
    expect($competition->fresh()->intro_video_seconds)->toBe(40);
});

it('clears the cached exam questions when the competition is saved', function () {
    $competition = app(SaveCompetition::class)->handle($this->manager, new Competition, examForm());
    ExamQuestions::load($competition);
    expect(Cache::has(ExamQuestions::key($competition->id)))->toBeTrue();

    app(SaveCompetition::class)->handle($this->manager, $competition, examForm(['title' => 'عنوان جديد']));

    expect(Cache::has(ExamQuestions::key($competition->id)))->toBeFalse();
});

it('locks the competition type after the first entry, and lets only managers save', function () {
    $competition = app(SaveCompetition::class)->handle($this->manager, new Competition, examForm());
    CompetitionSubmission::factory()->create(['competition_id' => $competition->id]);

    expect(fn () => app(SaveCompetition::class)->handle($this->manager, $competition, ['exam_mode' => false] + examForm()))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('exam_mode'));

    expect(fn () => app(SaveCompetition::class)->handle(userWithRole(RoleName::BoardMember), new Competition, examForm(['slug' => 'other'])))
        ->toThrow(AuthorizationException::class);
});

it('saves the allowed work types of a works competition', function () {
    $competition = app(SaveCompetition::class)->handle($this->manager, new Competition, examForm(['exam_mode' => false, 'intro_video_path' => null, 'work_types' => ['pdf', 'link']]));

    expect($competition->workTypeList())->toHaveCount(2);

    expect(fn () => app(SaveCompetition::class)->handle($this->manager, $competition, examForm(['exam_mode' => false, 'intro_video_path' => null, 'work_types' => ['video']])))
        ->toThrow(ValidationException::class);
});

it('caps question media by type, whatever the panel upload limit, and lets the panel upload reach the video cap', function () {
    // صوت فوق 25 ميجابايت: الرأس صوت حقيقي، والحجم فوق حده
    Storage::disk('local')->put('competition-media/long.mp3', mp3Contents().str_repeat("\0", 26 * 1024 * 1024));
    $form = examForm();
    $form['questions'][0] += ['media_type' => 'audio', 'media_path' => 'competition-media/long.mp3'];

    expect(fn () => app(SaveCompetition::class)->handle($this->manager, new Competition, $form))
        ->toThrow(ValidationException::class, __('competitions.exam_validation.media_size', ['number' => 1, 'max' => 25]))
        ->and(QuestionMedia::Image->maxKb())->toBe(10240)
        ->and(QuestionMedia::Video->maxKb())->toBe((int) config('competitions.exam.media_max_mb') * 1024)
        ->and(config('livewire.temporary_file_upload.rules'))->toContain('max:'.max(25600, QuestionMedia::Video->maxKb()));
});
