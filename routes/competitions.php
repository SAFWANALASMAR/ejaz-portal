<?php

use App\Http\Controllers\Account\CompetitionCertificateController;
use App\Http\Controllers\Account\CompetitionsController;
use App\Http\Controllers\Competitions\CompetitionController;
use App\Http\Controllers\Competitions\EntryController;
use App\Http\Controllers\Competitions\ExamController;
use App\Http\Controllers\Competitions\ReviewVideoController;
use App\Http\Controllers\Competitions\ReviewWorkController;
use App\Http\Controllers\Competitions\StudentExamController;
use App\Http\Controllers\Competitions\VideoUploadController;
use App\Http\Controllers\Competitions\WorkController;
use App\Http\Middleware\AdminIdleTimeout;
use Illuminate\Support\Facades\Route;

/*
| المسابقات (المرحلة 2 — docs/plans/competitions.md). تُحمَّل من routes/web.php.
*/

$slug = '[a-z0-9]+(?:-[a-z0-9]+)*';

Route::get('/competitions', [CompetitionController::class, 'index'])->name('competitions.index');
Route::get('/competitions/{slug}', [CompetitionController::class, 'show'])->where('slug', $slug)->name('competitions.show');
Route::get('/competitions/{slug}/guide', [CompetitionController::class, 'guide'])->where('slug', $slug)->name('competitions.student.guide');
// الرابط المختصر الثابت لمسابقة الطلاب (يُعمَّم على المدارس ورمز QR له). بلا حد لكل عنوان: مدرسة كاملة خلف عنوان
// واحد تمسح الرمز يوم الخميس معاً، والتحويل استعلام واحد بفهرس (مراجعة المشرف على المطوية)
Route::get('/c/{code}', [CompetitionController::class, 'short'])->where('code', '[a-z0-9]{6,12}')->name('competitions.short');

// التقديم: حساب مسجل، والمشاركة مشاركته وحدها (EntryController والإجراءات).
// ولكل مسار بادئة في حده: الحد بلا بادئة مفتاحه المستخدم وحده فتشترك فيه المسارات كلها، فيستهلك
// رفع الأجزاء والحفظ التلقائي حد الإرسال. والصفحات شخصية (المشاركة بإجاباتها، ومسابقاتي)، فلا تحفظها
// ذاكرة المتصفح كبقية صفحات الحساب
Route::middleware(['auth', 'cache.headers:private;no_store'])->where(['slug' => $slug])->group(function () {
    // المسودة تُنشأ بـ POST وحده وبحد معدل، فلا ينشئ تكرار فتح الرابط مسودات
    Route::post('/competitions/{slug}/entry/start', [EntryController::class, 'start'])->middleware('throttle:10,1,competition-entry-start')->name('competitions.entry.start');
    Route::get('/competitions/{slug}/entry', [EntryController::class, 'show'])->middleware('throttle:60,1,competition-entry')->name('competitions.entry');
    Route::post('/competitions/{slug}/entry', [EntryController::class, 'save'])->middleware('throttle:30,1,competition-entry-save')->name('competitions.entry.save');
    Route::post('/competitions/{slug}/entry/autosave', [EntryController::class, 'autosave'])->middleware('throttle:60,1,competition-entry-autosave')->name('competitions.entry.autosave');
    Route::post('/competitions/{slug}/entry/submit', [EntryController::class, 'submit'])->middleware('throttle:10,1,competition-entry-submit')->name('competitions.entry.submit');

    // رفع المقطع مجزأً: الأجزاء بروابط موقّعة مؤقتة، وكثيرة لكل مقطع فحدها أعلى
    Route::post('/competitions/{slug}/entry/videos/{question}', [VideoUploadController::class, 'start'])->whereNumber('question')->middleware('throttle:20,1,competition-video-start')->name('competitions.videos.start');
    Route::get('/competition-videos/{video}', [VideoUploadController::class, 'status'])->middleware('throttle:60,1,competition-video-status')->name('competitions.videos.status');
    Route::put('/competition-videos/{video}/chunks/{index}', [VideoUploadController::class, 'chunk'])->whereNumber('index')->middleware(['signed', 'throttle:600,1,competition-video-chunk'])->name('competitions.videos.chunk');
    Route::post('/competition-videos/{video}/complete', [VideoUploadController::class, 'complete'])->middleware('throttle:20,1,competition-video-complete')->name('competitions.videos.complete');

    Route::get('/account/competitions', CompetitionsController::class)->name('account.competitions');

    // مقطع المشاركة للمحكّم المسند إليه: يُفحص الإسناد عند كل طلب. والمشغّل يطلب أجزاءً عند التقديم
    Route::get('/competition-reviews/{review}/videos/{video}', ReviewVideoController::class)->middleware([AdminIdleTimeout::class, 'throttle:240,1,competition-review-video'])->name('competitions.reviews.video');

    // رحلة الاختبار الإلكتروني (docs/plans/competition-exam.md): كل مرحلة تُفحص في الإجراءات، والانتقال
    // بين الأسئلة يحفظ الإجابة فحده أعلى
    Route::get('/competitions/{slug}/exam', [ExamController::class, 'show'])->middleware('throttle:120,1,competition-exam')->name('competitions.exam');
    Route::post('/competitions/{slug}/exam/register', [ExamController::class, 'register'])->middleware('throttle:10,60,competition-exam-register')->name('competitions.exam.register');
    Route::post('/competitions/{slug}/exam/intro/start', [ExamController::class, 'introStart'])->middleware('throttle:30,1,competition-exam-intro')->name('competitions.exam.intro.start');
    Route::post('/competitions/{slug}/exam/intro/finish', [ExamController::class, 'introFinish'])->middleware('throttle:30,1,competition-exam-intro-finish')->name('competitions.exam.intro.finish');
    Route::get('/competitions/{slug}/exam/intro/video', [ExamController::class, 'introVideo'])->middleware('throttle:240,1,competition-exam-intro-video')->name('competitions.exam.intro.video');
    Route::post('/competitions/{slug}/exam/instructions', [ExamController::class, 'instructions'])->middleware('throttle:20,1,competition-exam-instructions')->name('competitions.exam.instructions');
    Route::get('/competitions/{slug}/exam/questions/{number}', [ExamController::class, 'question'])->whereNumber('number')->middleware('throttle:240,1,competition-exam-question')->name('competitions.exam.question');
    Route::get('/competitions/{slug}/exam/questions/{number}/media', [ExamController::class, 'questionMedia'])->whereNumber('number')->middleware('throttle:480,1,competition-exam-media')->name('competitions.exam.media');
    Route::post('/competitions/{slug}/exam/answer', [ExamController::class, 'answer'])->middleware('throttle:240,1,competition-exam-answer')->name('competitions.exam.answer');
    Route::get('/competitions/{slug}/exam/review', [ExamController::class, 'review'])->middleware('throttle:120,1,competition-exam-review')->name('competitions.exam.review');
    Route::post('/competitions/{slug}/exam/submit', [ExamController::class, 'submit'])->middleware('throttle:10,1,competition-exam-submit')->name('competitions.exam.submit');

    // الأعمال المرفوعة: «رفع الأعمال (صور – PDF – روابط)»
    Route::post('/competitions/{slug}/entry/works', [WorkController::class, 'store'])->middleware('throttle:20,1,competition-work-store')->name('competitions.works.store');
    Route::get('/competition-works/{work}', [WorkController::class, 'show'])->middleware('throttle:120,1,competition-work-show')->name('competitions.works.show');
    Route::delete('/competition-works/{work}', [WorkController::class, 'destroy'])->middleware('throttle:30,1,competition-work-destroy')->name('competitions.works.destroy');
    Route::get('/competition-reviews/{review}/works/{work}', ReviewWorkController::class)->middleware([AdminIdleTimeout::class, 'throttle:240,1,competition-review-work'])->name('competitions.reviews.work');

    // مسابقة الطلاب (docs/plans/student-competition.md). الحدود بعدّاد لكل حساب لا لكل عنوان، فطلاب المدرسة
    // الواحدة خلف عنوان شبكة واحد لا يتزاحمون. والانتقال بين الوحدات يحفظ الإجابات فحده أعلى
    Route::get('/competitions/{slug}/register', [StudentExamController::class, 'registerForm'])->middleware('throttle:60,1,competition-student-register-form')->name('competitions.student.register');
    Route::post('/competitions/{slug}/register', [StudentExamController::class, 'register'])->middleware('throttle:10,60,competition-student-register')->name('competitions.student.register.store');
    Route::prefix('/competitions/{slug}/rounds/{round}')->whereUuid('round')->group(function () {
        Route::get('/', [StudentExamController::class, 'show'])->middleware('throttle:120,1,competition-student-round')->name('competitions.student.round');
        Route::post('/start', [StudentExamController::class, 'start'])->middleware('throttle:20,1,competition-student-start')->name('competitions.student.start');
        Route::get('/units/{number}', [StudentExamController::class, 'unit'])->whereNumber('number')->middleware('throttle:240,1,competition-student-unit')->name('competitions.student.unit');
        Route::post('/units/{number}', [StudentExamController::class, 'save'])->whereNumber('number')->middleware('throttle:240,1,competition-student-save')->name('competitions.student.save');
        // حالة المحاولة بلا بدء ولا انتقال تلقائي (دليل الدخول: «إذا انقطع الاتصال قبل الإرسال»)
        Route::get('/status', [StudentExamController::class, 'status'])->middleware('throttle:120,1,competition-student-status')->name('competitions.student.status');
        Route::get('/review', [StudentExamController::class, 'review'])->middleware('throttle:120,1,competition-student-review')->name('competitions.student.review');
        Route::post('/submit', [StudentExamController::class, 'submit'])->middleware('throttle:20,1,competition-student-submit')->name('competitions.student.submit');
    });
    Route::get('/account/competitions/{submission}/certificate', CompetitionCertificateController::class)->whereUuid('submission')->middleware('throttle:30,1,competition-certificate')->name('account.competitions.certificate');
});
