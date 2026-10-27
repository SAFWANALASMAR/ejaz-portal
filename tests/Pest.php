<?php

use App\Actions\Competitions\RegisterStudent;
use App\Contracts\AiAssistant;
use App\Enums\QuestionType;
use App\Enums\RoleName;
use App\Enums\SaudiRegion;
use App\Enums\VideoStatus;
use App\Models\AssistantSettings;
use App\Models\Competition;
use App\Models\CompetitionCategory;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionRegion;
use App\Models\CompetitionRound;
use App\Models\CompetitionSubmission;
use App\Models\Meeting;
use App\Models\Setting;
use App\Models\SubmissionVideo;
use App\Models\User;
use App\Services\Assistant\FakeAiAssistant;
use App\Support\AccountSettings;
use App\Support\ContentCache;
use App\Support\RoundQuestions;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
| اختبارات السلوك (Feature) تعمل على قاعدة ejaz_testing وتُعاد لحالتها بعد كل اختبار.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// الدفع مؤرشف خلف PAYMENTS_ENABLED (معطّل افتراضياً — docs/plans/store-salla.md)، واختباراته تختبره مفعّلاً
pest()->beforeEach(function () {
    config(['payments.enabled' => true]);
})->in('Feature/Payments');

/**
 * مستخدم بدور محدد. الأدوار التي تشترط التحقق الثنائي يُفعَّل لها
 * حتى تمر من بوابة لوحة الإدارة في الاختبارات.
 *
 * @param  array<string, mixed>  $attributes
 */
function userWithRole(RoleName $role, array $attributes = []): User
{
    $factory = User::factory();

    if (in_array($role, RoleName::twoFactorRoles(), true)) {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->create($attributes);
    $user->assignRole($role->value);

    return $user;
}

/**
 * توثيق البريد والجوال شرطاً كما كان قبل تأجيله (AccountSettings — ملاحظات المدير التنفيذي، 25 أكتوبر 2026)، لاختبارات
 * مساراته. والبوابات المؤجلة تبقى كما هي ما لم تُمرَّر.
 */
function enableVerification(bool $showDeferredPortals = false): void
{
    Setting::put(AccountSettings::KEY, ['verification' => true, 'show_deferred_portals' => $showDeferredPortals]);
}

/**
 * بوابتا المجلس والموظفين والجمعية العمومية في حساب المستخدم ظاهرة، لاختبارات ما فيها.
 */
function showDeferredPortals(): void
{
    Setting::put(AccountSettings::KEY, ['verification' => AccountSettings::verificationEnabled(), 'show_deferred_portals' => true]);
}

/**
 * مقاطع الرئيسية السابقة (البطل والإحصائيات والاختصارات والمقطع والمبادرات والمرئيات والشركاء) ظاهرة، لاختباراتها. مخفية
 * افتراضياً منذ ملاحظات المدير التنفيذي (25 أكتوبر 2026).
 *
 * @param  array<string, mixed>  $home
 */
function showLegacyHomeSections(array $home = []): void
{
    Setting::put('home', ['show_hero' => true, 'show_video' => true, 'show_stats' => true, 'show_shortcuts' => true,
        'show_initiatives' => true, 'show_videos' => true, 'show_partners' => true, ...Setting::group('home'), ...$home]);
}

/**
 * اسم مستخدم فريد صالح للتسجيل (Username::PATTERN).
 */
function uniqueUsername(): string
{
    return 'u'.strtolower(Str::random(12));
}

/**
 * مسار مؤقت لقسم لم يُبنَ بعد، ليُختبر ما يظهر حين يُبنى (بطاقته في الرئيسية مثلاً).
 */
function registerSectionRoute(string $uri, string $name): void
{
    Route::get($uri, fn () => 'ok')->name($name);
    Route::getRoutes()->refreshNameLookups();
    ContentCache::flush();
}

/**
 * خانة إحصائية معتمدة لاختبارات الرئيسية.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function approvedStat(array $overrides = []): array
{
    return ['label' => 'مستفيد', 'value' => 25000, 'suffix' => '+', 'icon' => 'users', ...$overrides];
}

/**
 * محتوى PDF صغير صالح للاختبارات، مع إضافة اختيارية داخله (كائن فيه محتوى نشط مثلاً).
 */
function pdfContents(string $extra = ''): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        ."3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n".$extra."\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

/**
 * محتوى MP3 صغير للاختبارات: وسم ID3 ثم إطارات صوت فارغة، ويتعرف عليه نوعاً audio/mpeg.
 */
function mp3Contents(): string
{
    return "ID3\x04\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x64".str_repeat("\x00", 413), 4);
}

/**
 * مقطع MP4 صغير بمدته: صندوق ftyp، ثم moov فيه mvhd (الإصدار 0)، ثم بيانات بالحجم المطلوب.
 */
function mp4Contents(int $seconds = 30, int $padding = 0): string
{
    $box = fn (string $type, string $body): string => pack('N', 8 + strlen($body)).$type.$body;
    $mvhd = "\x00\x00\x00\x00".pack('N', 0).pack('N', 0).pack('N', 1000).pack('N', $seconds * 1000).str_repeat("\x00", 80);

    return $box('ftyp', 'isom'.pack('N', 512).'isomiso2mp41')
        .$box('moov', $box('mvhd', $mvhd))
        .$box('mdat', str_repeat("\x00", $padding));
}

/**
 * صورة صغيرة بنوعها (png أو webp، وغيرهما jpeg) وأبعادها، تولّدها GD.
 */
function imageBytes(string $extension = 'png', int $width = 8, int $height = 6): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    match ($extension) {
        'png' => imagepng($image),
        'webp' => imagewebp($image),
        default => imagejpeg($image),
    };

    return (string) ob_get_clean();
}

/**
 * ملف في مجلده على القرص الخاص المحلي باسم عشوائي، كما يتركه حقل الرفع قبل الإجراء. وبلا محتوى: PDF صالح.
 */
function storedPrivateFile(string $directory, ?string $contents = null, string $extension = 'pdf'): string
{
    $path = $directory.'/'.fake()->uuid().'.'.$extension;
    Storage::disk('local')->put($path, $contents ?? pdfContents());

    return $path;
}

/**
 * مشاركة مرسلة بإجابة ومنطقة ومقطع محفوظ على القرص المحلي (اختبارات مدة الاحتفاظ).
 */
function entryWithVideo(Competition $competition, User $user): CompetitionSubmission
{
    $submission = CompetitionSubmission::factory()->submitted()->create([
        'competition_id' => $competition->id,
        'user_id' => $user->id,
        'category' => 'الفئة الأولى',
        'region' => SaudiRegion::Makkah,
        'answers' => [(string) $competition->questions[0]->id => 'نص المتقدم'],
        'auto_score' => 7,
        'final_score' => 8.5,
    ]);

    $path = 'competition-videos/'.$submission->uuid.'.mp4';
    Storage::disk('local')->put($path, 'video-bytes');

    $video = new SubmissionVideo;
    $video->submission_id = $submission->id;
    $video->question_id = $competition->questions[2]->id;
    $video->status = VideoStatus::Ready;
    $video->size = 11;
    $video->chunk_count = 1;
    $video->duration_seconds = 30;
    $video->path = $path;
    $video->save();

    return $submission;
}

/**
 * القرص المحلي يتعذر عليه حذف المسارات المذكورة، فيعيد false بلا استثناء كما تفعل الأقراص المضبوطة
 * بـ throw: false. ويعيد القرص الأصلي لإعادة المحاولة بعده (Storage::set).
 */
function failDeletesOf(string ...$paths): FilesystemAdapter
{
    /** @var FilesystemAdapter $original */
    $original = Storage::disk('local');

    Storage::set('local', new class($original, array_values($paths)) extends FilesystemAdapter
    {
        /**
         * @param  list<string>  $failing
         */
        public function __construct(FilesystemAdapter $original, private array $failing)
        {
            parent::__construct($original->getDriver(), $original->getAdapter(), $original->getConfig());
        }

        public function delete($paths)
        {
            return array_intersect($this->failing, (array) $paths) !== [] ? false : parent::delete($paths);
        }
    });

    return $original;
}

/**
 * يشغّل المساعد الذكي بالمزود الوهمي (للاختبارات)، بسقف وسعر فيعمل التوليد، ويعيد المزود الوهمي ليُفحص ما أُرسل إليه.
 *
 * @param  array<string, mixed>  $attributes
 */
function enableAssistant(array $attributes = []): FakeAiAssistant
{
    // نطاق المزود المتوافق في الاختبارات مسموح كما يُضبط في .env على الخادم
    config(['assistant.allowed_hosts' => ['api.anthropic.com', 'api.openai.com', 'models.example.sa']]);
    AssistantSettings::query()->delete();
    AssistantSettings::query()->create([
        'is_enabled' => true,
        'generation_enabled' => true,
        'provider' => 'fake',
        'monthly_cap' => 10,
        'input_price' => 1,
        'output_price' => 5,
        ...$attributes,
    ]);
    Cache::forget(AssistantSettings::CACHE_KEY);

    $fake = new FakeAiAssistant;
    app()->instance(AiAssistant::class, $fake);

    return $fake;
}

/**
 * حدث تقويم كما يرده Graph لاجتماع بعنوانه وموعده الحاليين (فلا يُحدَّث بعد إنشائه)، مع ما يُضاف إليه.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function teamsEvent(Meeting $meeting, array $extra = []): array
{
    $at = fn ($time): array => ['dateTime' => $time->copy()->utc()->format('Y-m-d\TH:i:s').'.0000000', 'timeZone' => 'UTC'];

    return ['subject' => $meeting->title, 'start' => $at($meeting->starts_at), 'end' => $at($meeting->ends_at ?? $meeting->starts_at->copy()->addHour()), ...$extra];
}

/**
 * رقم هوية سعودي صحيح الصيغة ورقم التحقق من رقم تسلسلي (مسابقة الطلاب).
 */
function studentNationalId(int $serial): string
{
    $body = '1'.str_pad((string) $serial, 8, '0', STR_PAD_LEFT);
    $sum = 0;

    foreach (str_split($body) as $position => $digit) {
        $number = (int) $digit * ($position % 2 === 0 ? 2 : 1);
        $sum += $number > 9 ? $number - 9 : $number;
    }

    return $body.((10 - $sum % 10) % 10);
}

/**
 * سؤال اختيار في بنك مرحلة من مسابقة الطلاب، الصحيح فيه الخيار b، أو صح وخطأ الصحيح فيه «صح». ويُضبط عدد أسئلة
 * المرحلة على بنك الطالب الافتراضي (registeredStudent: المرحلة الثانوية)، لأن البنك بالعدد تماماً شرط البدء.
 * والاختبار الذي يختبر البنك الناقص أو الزائد يضبط العدد بنفسه بعده.
 *
 * @param  array<string, mixed>  $attributes
 */
function studentQuestion(CompetitionRound $round, array $attributes = []): CompetitionQuestion
{
    $question = new CompetitionQuestion([
        'round_id' => $round->id,
        'type' => QuestionType::MultipleChoice,
        'prompt' => 'سؤال تجريبي '.Str::random(6),
        'options' => [['key' => 'a', 'label' => 'خيار أ'], ['key' => 'b', 'label' => 'خيار ب'], ['key' => 'c', 'label' => 'خيار ج'], ['key' => 'd', 'label' => 'خيار د']],
        'correct_option' => 'b',
        'points' => 1,
        'is_required' => false,
        ...$attributes,
    ]);
    $question->competition_id = $round->competition_id;
    $question->save();

    $secondary = CompetitionCategory::query()->where('name', 'المرحلة الثانوية')->value('id');
    $round->forceFill(['question_count' => $round->questions()->where(fn ($query) => $query->whereNull('category_id')->orWhere('category_id', $secondary))->count()])->save();
    RoundQuestions::forget($round->id);

    return $question;
}

/**
 * تسجيل طالب في مسابقة الطلاب بحساب بلا جوال موثّق، عبر الإجراء نفسه.
 *
 * @param  array<string, mixed>  $overrides
 */
function registeredStudent(Competition $competition, int $serial = 1, array $overrides = []): User
{
    $user = User::factory()->create(['phone' => null, 'phone_verified_at' => null]);
    $category = CompetitionCategory::query()->where('name', 'المرحلة الثانوية')->firstOrFail();

    app(RegisterStudent::class)->handle($user, $competition, [
        'full_name' => 'طالب تجريبي بن مثال',
        'national_id' => studentNationalId($serial),
        'category_id' => $category->id,
        'grade' => 'الأول الثانوي',
        'region_id' => CompetitionRegion::query()->where('is_active', true)->value('id'),
        'gender' => 'male',
        'pledge' => '1',
        ...$overrides,
    ]);

    return $user;
}

/**
 * حارس المشغّل المحلي على مهمتي الفحص الآلي: لا نسخة (fork) ولا Dependabot (docs/deploy/ci-runner.md «الأمن»).
 */
function ciRunnerGuard(): string
{
    return "(github.event_name != 'pull_request' || github.event.pull_request.head.repo.full_name == github.repository) && github.actor != 'dependabot[bot]' && !startsWith(github.head_ref, 'dependabot/') && !startsWith(github.ref_name, 'dependabot/')";
}
