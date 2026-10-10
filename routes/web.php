<?php

use App\Enums\LibrarySection;
use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\DataRightsController;
use App\Http\Controllers\Account\PhoneVerificationController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FallbackController;
use App\Http\Controllers\GovernanceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InitiativeController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\LiveStreamController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
| مسارات الدخول والتسجيل والاستعادة والتحقق الثنائي يسجلها Fortify (config/fortify.php).
*/

Route::get('/', HomeController::class)->name('home');

// المحتوى المؤسسي
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/pages/{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('pages.show');
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
});
// المركز الإعلامي: الأخبار، ومكتبة الصور، والشركاء. ومكتبة الفيديو هي المكتبة المرئية
Route::get('/media', [MediaController::class, 'index'])->name('media.index');
Route::get('/media/partners', [MediaController::class, 'partners'])->name('media.partners');
Route::get('/media/news', [NewsController::class, 'index'])->name('media.news.index');
Route::get('/media/news/{slug}', [NewsController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('media.news.show');
Route::get('/media/albums', [AlbumController::class, 'index'])->name('media.albums.index');
Route::get('/media/albums/{slug}', [AlbumController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('media.albums.show');

// التواصل: الصفحة ونموذج «الاستفسارات والاقتراحات»، بحدود لكل مشترك وللبوابة (FortifyServiceProvider)
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

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

    Route::get('/verify-phone', [PhoneVerificationController::class, 'show'])->name('phone.verify');
    Route::post('/verify-phone', [PhoneVerificationController::class, 'verify'])->middleware('throttle:10,1')->name('phone.verify.store');
    Route::post('/verify-phone/send', [PhoneVerificationController::class, 'send'])->middleware('throttle:6,1')->name('phone.send');

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

// البث المباشر (المرحلة 5): يظهر ويختفي بالموعد (docs/plans/livestream.md)
Route::get('/live', LiveStreamController::class)->middleware('throttle:60,1')->name('live');
