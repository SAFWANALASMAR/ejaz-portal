<?php

use App\Enums\LibrarySection;
use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\DataRightsController;
use App\Http\Controllers\Account\MyReadingController;
use App\Http\Controllers\Account\NewPasswordController;
use App\Http\Controllers\Account\PasswordResetByPhoneController;
use App\Http\Controllers\Account\PhoneVerificationController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EvidenceGalleryController;
use App\Http\Controllers\FallbackController;
use App\Http\Controllers\GovernanceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InitiativeController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\LibraryProgressController;
use App\Http\Controllers\LiveStreamController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PagePreviewController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\AdminIdleTimeout;
use App\Http\Middleware\EnsureAssistantEnabled;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;

/*
| مسارات الدخول والتسجيل والاستعادة والتحقق الثنائي يسجلها Fortify (config/fortify.php).
| وطلب رابط الاستعادة يُعاد تسجيله هنا بالاسم والمسار نفسيهما ليأخذ حداً لكل شبكة، فمسارات التطبيق
| تُحمَّل بعد Fortify فتحل محل مساره.
*/

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware(['guest:'.config('fortify.guard'), 'throttle:password-reset'])
    ->name('password.email');

// وتعيين كلمة المرور الجديدة برمز الرابط كذلك، بحد لكل بريد وعنوان ولكل مشترك وفي زمن ثابت: كان بلا حد، وكل طلب
// تجزئة bcrypt، وزمنه يكشف الحساب (الاختبار الأمني 0.14.0)
Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware(['guest:'.config('fortify.guard'), 'throttle:password-update'])
    ->name('password.update');

// الاستعادة برمز الجوال (قرار صفوان، 20 أكتوبر 2026): طلب الرمز بحد الاستعادة نفسه، ثم صفحة الرمز وكلمة المرور الجديدة
Route::middleware('guest:'.config('fortify.guard'))->group(function () {
    Route::post('/forgot-password/phone', [PasswordResetByPhoneController::class, 'send'])->middleware('throttle:password-reset')->name('password.phone.send');
    Route::get('/forgot-password/phone', [PasswordResetByPhoneController::class, 'edit'])->name('password.phone.reset');
    Route::post('/forgot-password/phone/reset', [PasswordResetByPhoneController::class, 'update'])->middleware('throttle:10,1')->name('password.phone.update');
});

// Filament يسجّل مسار «صفوف الاستيراد الفاشلة» دائماً، والبوابة لا تستورد ولا جدول imports فيها: كان الربط الضمني يسأل
// جدولاً غير موجود فيرد 500 لأي زائر. فيُعاد تسجيله هنا بالاسم والمسار نفسيهما بلا نموذج مربوط، فيرد 404 قبل أي استعلام،
// ولا يمس ربطٌ عام معامل {import} في أي مسار آخر (مراجعة المشرف على #64)
Route::get('/filament/imports/{import}/failed-rows/download', fn () => abort(404))->name('filament.imports.failed-rows.download');

Route::get('/', HomeController::class)->name('home');

// المحتوى المؤسسي
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/pages/{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('pages.show');
// معاينة نسخة من صفحة ثابتة قبل نشرها («محرر الصفحات»): رابط موقّع قصير المدة، بعد الدخول والتحقق الثنائي، ولمن له
// pages.edit_all وحده (PagePreviewController)، بلا تخزين ولا فهرسة
Route::get('/page-preview/{revision}', PagePreviewController::class)
    ->middleware(['auth', 'auth.session', EnsureTwoFactorIsEnabled::class, AdminIdleTimeout::class, 'signed', 'throttle:60,1', 'cache.headers:private;no_store'])
    ->whereNumber('revision')
    ->name('pages.preview');
Route::get('/governance', [GovernanceController::class, 'index'])->name('governance.index');
Route::get('/governance/{slug}', [GovernanceController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('governance.show');
// الملف يُبث من الخادم، فحد معقول لكل عنوان. وفي الإنتاج يُسلَّم للخادم أو لرابط تخزين موقّع (roadmap.md)
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/governance/{slug}/file', [GovernanceController::class, 'file'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('governance.file');
    Route::get('/governance/{slug}/download', [GovernanceController::class, 'download'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('governance.download');
});
Route::get('/initiatives', [InitiativeController::class, 'index'])->name('initiatives.index');
Route::get('/initiatives/{slug}', [InitiativeController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('initiatives.show');

// المكتبة بأقسامها الأربعة
Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
Route::where(['section' => implode('|', LibrarySection::values()), 'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])->group(function () {
    Route::get('/library/{section}', [LibraryController::class, 'section'])->name('library.section');
    Route::get('/library/{section}/{slug}', [LibraryController::class, 'show'])->name('library.show');
    // الملف يُبث من الخادم، فحد لكل عنوان. ومشغّل الصوت يطلب أجزاءً من الملف عند التقديم والتأخير
    Route::middleware('throttle:120,1')->group(function () {
        Route::get('/library/{section}/{slug}/file', [LibraryController::class, 'file'])->name('library.file');
        Route::get('/library/{section}/{slug}/download', [LibraryController::class, 'download'])->name('library.download');
    });
    // «قراءاتي»: بلاغ تقدّم القارئ والمشغّل لمسجّل الدخول، بحد لكل حساب (docs/decisions/2026-11-01-my-reading.md)
    Route::post('/library/{section}/{slug}/progress', LibraryProgressController::class)
        ->middleware(['auth', 'throttle:library-progress'])
        ->name('library.progress');
});
// المركز الإعلامي: الأخبار، ومكتبة الصور، والشركاء. ومكتبة الفيديو هي المكتبة المرئية
Route::get('/media', [MediaController::class, 'index'])->name('media.index');
Route::get('/media/partners', [MediaController::class, 'partners'])->name('media.partners');
Route::get('/media/news', [NewsController::class, 'index'])->name('media.news.index');
Route::get('/media/news/{slug}', [NewsController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('media.news.show');
Route::get('/media/announcements', [MediaController::class, 'announcements'])->name('media.announcements');
Route::get('/media/albums', [AlbumController::class, 'index'])->name('media.albums.index');
Route::get('/media/albums/{slug}', [AlbumController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('media.albums.show');

// التواصل: الصفحة ونموذج «الاستفسارات والاقتراحات»، بحدود لكل مشترك وللبوابة (FortifyServiceProvider)
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

// المساعد الذكي (requirements/10): معطّلاً يرد 404، وحد الأسئلة لكل زائر (FortifyServiceProvider)
Route::middleware(EnsureAssistantEnabled::class)->prefix('assistant')->name('assistant.')->group(function () {
    Route::post('/ask', [AssistantController::class, 'ask'])->middleware('throttle:assistant')->name('ask');
    Route::post('/answers/{answer}/rating', [AssistantController::class, 'rate'])
        ->whereUuid('answer')
        ->middleware('throttle:assistant-rating')
        ->name('rate');
});

// لمحركات البحث: الخريطة من المنشور، وrobots بمفتاح الفهرسة
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
    Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
});

// كل طلب GET لا يطابق مساراً: تحويل رابط قديم مسجل، وإلا 404 العربية. ولارافيل يطابقه آخراً أينما سُجّل
Route::fallback(FallbackController::class);

// لوحة المستخدم. صفحاتها شخصية، فلا تحفظها ذاكرة المتصفح (كصفحات الطلبات والبرامج والمسابقات)
Route::middleware(['auth', 'cache.headers:private;no_store'])->prefix('account')->name('account.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
    Route::get('/security', SecurityController::class)->middleware('password.confirm')->name('security');
    Route::post('/security/other-sessions', [SecurityController::class, 'logoutOtherSessions'])->middleware('throttle:10,1')->name('security.other-sessions');

    Route::get('/verify-phone', [PhoneVerificationController::class, 'show'])->name('phone.verify');
    Route::post('/verify-phone', [PhoneVerificationController::class, 'verify'])->middleware('throttle:10,1')->name('phone.verify.store');
    Route::post('/verify-phone/send', [PhoneVerificationController::class, 'send'])->middleware('throttle:6,1')->name('phone.send');

    Route::get('/my-reading', [MyReadingController::class, 'show'])->name('my-reading');
    Route::post('/my-reading/clear', [MyReadingController::class, 'clear'])->middleware('throttle:10,1')->name('my-reading.clear');
    Route::post('/my-reading/tracking', [MyReadingController::class, 'tracking'])->middleware('throttle:10,1')->name('my-reading.tracking');

    Route::get('/data', [DataRightsController::class, 'show'])->name('data');
    Route::get('/data/export', [DataRightsController::class, 'export'])->middleware(['password.confirm', 'throttle:5,60'])->name('data.export');
    Route::post('/data/deletion', [DataRightsController::class, 'requestDeletion'])->middleware('throttle:5,60')->name('data.deletion');
});

// الخدمات الإلكترونية
require __DIR__.'/services.php';

// البرامج والدورات
require __DIR__.'/programs.php';

// المسابقات (المرحلة 2)
require __DIR__.'/competitions.php';

// قياس الرضا (المرحلة 2)
require __DIR__.'/surveys.php';

// الجمعية العمومية (المرحلة 4)
require __DIR__.'/assembly.php';

// المتجر والدفع والتبرعات (المرحلة 5)
require __DIR__.'/payments.php';

// السجلات والمستندات ذات الصلاحية: تنزيل المرفقات من اللوحة برابط موقّع (docs/plans/registers.md)
require __DIR__.'/registers.php';

// النماذج الإلكترونية: عرض الطباعة و«نماذجي» (المرحلة 4)
require __DIR__.'/documents.php';

// ملفات الوظائف: تنزيل مستنداتها، والشاشات في لوحة الإدارة
require __DIR__.'/job-files.php';

// «إنجازاتنا»: الصفحة العامة للمنشور ومرفقه برابط موقّع، والإدارة في اللوحة
require __DIR__.'/achievements.php';

// قنوات التواصل: إشعارات حالة تسليم واتساب (docs/guides/messaging.md)
require __DIR__.'/messaging.php';

// معرض الشواهد برابط سري بلا حساب (docs/decisions.md — 30 أكتوبر 2026): الرمز في المسار بأي صيغة حتى يرد كل رفض
// 404 واحدة من المتحكم نفسه وبحده، والملفات برابط موقّع قصير بعد فحص الرمز. ولا تُفهرس ولا تُخزَّن (SecurityHeaders)
Route::middleware('cache.headers:private;no_store')->prefix('evidence')->name('evidence.')->group(function () {
    Route::get('/files/{item}/{key}', [EvidenceGalleryController::class, 'file'])->whereNumber('item')->where('key', '[a-f0-9]{24}')->middleware('throttle:evidence-files')->name('file');
    Route::get('/{token}', [EvidenceGalleryController::class, 'show'])->where('token', '[^/]{1,200}')->middleware('throttle:evidence')->name('show');
});

// البث المباشر (المرحلة 5): يظهر ويختفي بالموعد (docs/plans/livestream.md)
Route::get('/live', LiveStreamController::class)->middleware('throttle:60,1')->name('live');
