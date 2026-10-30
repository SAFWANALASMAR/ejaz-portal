<?php

use App\Actions\Competitions\CorrectStudentRegistration;
use App\Enums\RoleName;
use App\Models\Competition;
use App\Models\CompetitionCategory;
use App\Models\CompetitionRegion;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\RoundQuestions;
use Database\Seeders\BrahinCompetitionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
| ملاحظات المدير التنفيذي على صفحة مسابقة «البراهين العلمية» (29 أكتوبر 2026، قرار صفوان: «زي ما قال المدير»): ثلاث فئات
| بلا المتوسطة، و«رقم الجوال» والجهة التعليمية إلزاميان للجميع، ولا بند «توجيهات لجنة التحكيم»، و«المرحلة» بدل «الفرع».
| وترحيل 2026_10_29_100000 يطبّقها على البيانات المخزنة.
*/

beforeEach(function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);

    (new BrahinCompetitionSeeder)->run();
    $this->competition = Competition::query()->where('slug', 'brahin-1448')->sole();
    $this->competition->forceFill(['is_published' => true])->save();
    $this->school = $this->competition->fields()->sole();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function brahinForm(array $overrides = []): array
{
    return [
        'full_name' => 'عبدالله محمد أحمد الغامدي',
        'national_id' => studentNationalId(31),
        'category_id' => CompetitionCategory::query()->where('name', 'المرحلة الثانوية')->value('id'),
        'grade' => 'الأول الثانوي',
        'region_id' => CompetitionRegion::query()->where('is_active', true)->value('id'),
        'guardian_phone' => '0551234567',
        'gender' => 'male',
        'pledge' => '1',
        'fields' => [test()->school->id => 'ثانوية تجريبية'],
        ...$overrides,
    ];
}

it('offers three categories without the intermediate stage, and its grades nowhere', function () {
    $page = (string) $this->actingAs(User::factory()->create())->get('/competitions/brahin-1448/register')->assertOk()->getContent();

    expect($this->competition->openCategories()->pluck('name')->all())->toBe(['المرحلة الثانوية', 'المرحلة الجامعية', 'المهتمون بالثقافة العلمية'])
        ->and(CompetitionCategory::query()->where('name', 'المرحلة المتوسطة')->exists())->toBeFalse()
        ->and($page)->not->toContain('المتوسطة')->not->toContain('الأول المتوسط');
});

it('requires the mobile number and the school from everyone, on the form and on the server', function (string $missing, array $overrides) {
    $page = (string) $this->actingAs(User::factory()->create())->get('/competitions/brahin-1448/register')->getContent();

    expect($page)->toContain(__('competitions.student.attributes.guardian_phone'))
        ->not->toContain('ولي الأمر')
        ->toMatch('/name="guardian_phone"[^>]*required/')
        ->toMatch('/name="fields\['.$this->school->id.'\]"[^>]*required/');

    $this->post('/competitions/brahin-1448/register', brahinForm($overrides))->assertSessionHasErrors($missing === 'school' ? 'fields.'.$this->school->id : $missing);

    expect(CompetitionSubmission::query()->count())->toBe(0);

    // وبهما يُقبل
    $this->post('/competitions/brahin-1448/register', brahinForm())->assertSessionHasNoErrors();
    expect(CompetitionSubmission::query()->count())->toBe(1);
})->with([
    'no mobile number' => ['guardian_phone', ['guardian_phone' => '']],
    'no school' => ['school', ['fields' => []]],
]);

it('shows the rules without the judging committee item, and names the rounds stages', function () {
    $page = (string) $this->get('/competitions/brahin-1448')->assertOk()->getContent();

    expect($page)->not->toContain('لجنة التحكيم')
        ->not->toContain('الفرع الأول')->not->toContain('الفروع')
        ->toContain('المرحلة الأولى')->toContain('المراحل');
});

it('applies the notes to the stored data, keeping a category that registrations point to', function () {
    $migration = require database_path('migrations/2026_10_29_100000_apply_brahin_director_notes.php');

    // البيانات كما كانت قبل الملاحظات
    $middle = CompetitionCategory::query()->create(['name' => 'المرحلة المتوسطة', 'grades' => ['الأول المتوسط'], 'sort_order' => 0]);
    $this->competition->openCategories()->attach($middle->id);
    $this->competition->rounds()->orderBy('sort_order')->first()->update(['name' => 'الفرع الأول']);
    $this->competition->rounds()->orderBy('sort_order')->skip(1)->first()->update(['name' => 'جولة سمّاها المحرر']);
    $this->competition->forceFill(['page_content' => [...$this->competition->page_content, 'rules' => [
        'أن يكون المتسابق سعوديًا أو مقيمًا بإقامة نظامية سارية المفعول.',
        'الالتزام بضوابط المسابقة وتوجيهات لجنة التحكيم.',
        'المشاركة في جميع الفروع خلال الأسابيع المحددة.',
    ]]])->save();
    $this->school->update(['is_required' => false]);

    $migration->up();

    $competition = $this->competition->fresh();
    expect(CompetitionCategory::query()->whereKey($middle->id)->exists())->toBeFalse()
        ->and($competition->rounds()->orderBy('sort_order')->pluck('name')->take(2)->all())->toBe(['المرحلة الأولى', 'جولة سمّاها المحرر'])
        ->and($competition->pageList('rules'))->toBe(['أن يكون المتسابق سعوديًا أو مقيمًا بإقامة نظامية سارية المفعول.', 'المشاركة في جميع المراحل خلال الأسابيع المحددة.'])
        ->and($this->school->fresh()->is_required)->toBeTrue();

    // فئة سجّل فيها طالب: تُغلق ولا تُحذف، فلا ينكسر التسجيل
    $kept = CompetitionCategory::query()->create(['name' => 'المرحلة المتوسطة', 'grades' => ['الأول المتوسط'], 'sort_order' => 0]);
    $this->competition->openCategories()->attach($kept->id);
    $student = registeredStudent($this->competition, 41, ['category_id' => $kept->id, 'grade' => 'الأول المتوسط']);

    $migration->up();

    expect(CompetitionCategory::query()->whereKey($kept->id)->exists())->toBeTrue()
        ->and(DB::table('competition_category_competition')->where('category_id', $kept->id)->exists())->toBeFalse()
        ->and(CompetitionSubmission::query()->where('user_id', $student->id)->value('category_id'))->toBe($kept->id);
});

it('lets a registrant in the closed intermediate stage be corrected in their current category, and refreshes the round banks', function () {
    $migration = require database_path('migrations/2026_10_29_100000_apply_brahin_director_notes.php');
    $middle = CompetitionCategory::query()->create(['name' => 'المرحلة المتوسطة', 'grades' => ['الأول المتوسط'], 'sort_order' => 0]);
    $this->competition->openCategories()->attach($middle->id);
    $student = registeredStudent($this->competition, 42, ['category_id' => $middle->id, 'grade' => 'الأول المتوسط']);
    $submission = CompetitionSubmission::query()->where('user_id', $student->id)->sole();
    $round = $this->competition->rounds()->orderBy('sort_order')->firstOrFail();
    $version = RoundQuestions::key($round->id);

    $migration->up();

    expect(RoundQuestions::key($round->id))->not->toBe($version);

    $director = userWithRole(RoleName::ExecutiveDirector);
    $data = ['reason' => 'تصحيح الاسم بطلب من المدرسة', 'full_name' => 'عبدالله محمد أحمد الزهراني', 'category_id' => $middle->id, 'grade' => 'الأول المتوسط',
        'region_id' => $submission->region_id, 'gender' => 'male'];

    app(CorrectStudentRegistration::class)->edit($director, $submission, $data);
    expect($submission->fresh()->full_name)->toBe('عبدالله محمد أحمد الزهراني')->and($submission->fresh()->category_id)->toBe($middle->id);

    // ولا تُختار الفئة المغلقة لتسجيل آخر
    $other = CompetitionSubmission::query()->where('user_id', registeredStudent($this->competition, 43)->id)->sole();
    expect(fn () => app(CorrectStudentRegistration::class)->edit($director, $other, [...$data, 'region_id' => $other->region_id]))->toThrow(ValidationException::class);
});
