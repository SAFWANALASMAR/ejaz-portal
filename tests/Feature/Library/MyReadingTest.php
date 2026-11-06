<?php

use App\Actions\Accounts\ExportUserData;
use App\Actions\Library\RecordLibraryProgress;
use App\Actions\Library\SaveLibraryItem;
use App\Actions\Users\AnonymizeUser;
use App\Enums\LibrarySection;
use App\Enums\RoleName;
use App\Jobs\CountLibraryPages;
use App\Models\LibraryItem;
use App\Models\LibraryProgress;
use App\Models\User;
use App\Support\HomePortalStats;
use App\Support\LibraryPageCount;
use App\Support\PageSet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
| «قراءاتي» (docs/decisions/2026-11-01-my-reading.md): القارئ والمشغّل يبلّغان عن تقدّم صاحب الحساب في العناصر المنشورة، بحد
| وتحقق (الصفحة ضمن عدد صفحات الملف في الخادم، والثواني ضمن الوقت المنقضي)، وتظهر له وحده في رئيسية اللوحة وصفحة «قراءاتي»،
| وتُحذف بحذف حسابه، وللإدارة مجاميعها وحدها.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->reader = User::factory()->create();
});

/**
 * PDF بعدد صفحاته: عقدة /Pages بقاموس متداخل (الموارد) قبل /Count، ثم كائن لكل صفحة.
 */
function readingPdf(int $pages): string
{
    $kids = implode(' ', array_map(fn (int $i): string => (3 + $i).' 0 R', range(0, $pages - 1)));
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        ."2 0 obj\n<< /Type /Pages /Resources << /Font << /F1 9 0 R >> >> /Kids [{$kids}] /Count {$pages} >>\nendobj\n";

    foreach (range(0, $pages - 1) as $i) {
        $pdf .= (3 + $i)." 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
    }

    return $pdf."trailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

/**
 * @param  array<string, mixed>  $attributes
 */
function readingBook(int $pages = 5, array $attributes = []): LibraryItem
{
    $item = LibraryItem::factory()->create(['slug' => 'reading-book', 'title' => 'كتاب القراءة', 'is_downloadable' => false, ...$attributes]);
    Storage::disk(LibraryItem::disk())->put((string) $item->file, readingPdf($pages));
    // العدد كما تحسبه المهمة بعد حفظ الملف
    countPages($item);

    return $item->refresh();
}

function legacyAudio(): LibraryItem
{
    return LibraryItem::factory()->audio()->create(['slug' => 'reading-audio', 'title' => 'مادة صوتية للاستماع']);
}

/**
 * @param  array<string, mixed>  $data
 */
function reportProgress(LibraryItem $item, array $data = []): TestResponse
{
    return test()->postJson(route('library.progress', [$item->section->value, $item->slug]), $data);
}

function progressOf(User $user, LibraryItem $item): ?LibraryProgress
{
    return LibraryProgress::query()->whereBelongsTo($user)->where('library_item_id', $item->id)->first();
}

it('counts the pages of a book from its file in the queue, also inside compressed object streams', function () {
    expect(LibraryPageCount::count(readingPdf(7)))->toBe(7)
        ->and(LibraryPageCount::count(pdfContents()))->toBe(1)
        ->and(LibraryPageCount::count('not a pdf'))->toBeNull();

    $objects = gzcompress('<< /Type /Pages /Kids [4 0 R 5 0 R] /Count 12 >> << /Type /Page /Parent 3 0 R >>');
    $compressed = "%PDF-1.5\n1 0 obj\n<< /Type /ObjStm /N 2 /First 10 /Filter /FlateDecode /Length ".strlen($objects)." >>\nstream\n{$objects}\nendstream\nendobj\n%%EOF\n";
    expect(LibraryPageCount::count($compressed))->toBe(12);

    expect(LibraryPageCount::for(readingBook(9)))->toBe(9)
        ->and(LibraryPageCount::for(legacyAudio()))->toBeNull();
});

it('never reads the file on the report path: an uncounted book queues one count and is treated as unknown', function () {
    Queue::fake();
    $book = LibraryItem::factory()->create(['slug' => 'uncounted', 'is_downloadable' => false]);
    Storage::disk('local')->put((string) $book->file, readingPdf(4));
    $this->actingAs($this->reader);

    reportProgress($book, ['pages' => [1], 'page' => 1])->assertOk();

    Queue::assertPushed(CountLibraryPages::class, fn (CountLibraryPages $job): bool => $job->itemId === $book->id);
    expect(progressOf($this->reader, $book)->total_pages)->toBeNull()
        ->and(progressOf($this->reader, $book)->completed_at)->toBeNull();
});

it('counts the pages again when the library item gets a new file', function () {
    Queue::fake();
    $book = readingBook(5);
    $book->forceFill(['page_count' => 5])->save();
    $file = storedPrivateFile(LibraryItem::FILE_DIRECTORY, readingPdf(3));

    app(SaveLibraryItem::class)->handle(userWithRole(RoleName::ContentEditor), $book, ['file' => $file]);

    expect($book->fresh()->page_count)->toBeNull();
    Queue::assertPushed(CountLibraryPages::class);

    countPages($book);
    expect($book->fresh()->page_count)->toBe(3);
});

it('keeps the seen pages as compact sorted ranges', function () {
    expect(PageSet::encode([3, 1, 2, 2, 7, 9, 8, 15]))->toBe('1-3,7-9,15')
        ->and(PageSet::decode('1-3,7-9,15'))->toBe([1, 2, 3, 7, 8, 9, 15])
        ->and(PageSet::encode([]))->toBeNull()
        ->and(PageSet::decode('x,0,5-2,4'))->toBe([4])
        ->and(count(PageSet::decode('1-999999')))->toBe(PageSet::MAX_PAGE);
});

it('records the pages a signed-in reader sees in the book reader, idempotently, and completes the book', function () {
    $book = readingBook(5);
    $this->actingAs($this->reader);

    reportProgress($book, ['pages' => [1], 'page' => 1])->assertOk()->assertExactJson(['pages' => [1]]);
    $progress = progressOf($this->reader, $book);
    expect($progress->pages_count)->toBe(1)->and($progress->total_pages)->toBe(5)->and($progress->last_page)->toBe(1)->and($progress->completed_at)->toBeNull();

    $this->travel(20)->seconds();
    reportProgress($book, ['pages' => [2, 3, 4], 'page' => 4])->assertOk()->assertExactJson(['pages' => [2, 3, 4]]);
    $this->travel(20)->seconds();
    // البلاغ نفسه مرة أخرى لا يغيّر العدد، وصفحاته مقبولة من قبل
    reportProgress($book, ['pages' => [2, 3, 4], 'page' => 4])->assertOk()->assertExactJson(['pages' => [2, 3, 4]]);
    expect(progressOf($this->reader, $book)->pages_count)->toBe(4)->and(progressOf($this->reader, $book)->pages_seen)->toBe('1-4');

    $this->travel(20)->seconds();
    reportProgress($book, ['pages' => [5], 'page' => 5])->assertOk();
    expect(progressOf($this->reader, $book)->completed_at)->not->toBeNull()
        ->and(LibraryProgress::query()->count())->toBe(1);
});

it('does not let a scripted report pass a whole book at once, and returns the pages it left for later', function () {
    $book = readingBook(100);
    $this->actingAs($this->reader);

    // ثلاث صفحات لحساب لم يبلّغ قبل، ثم صفحة لكل ثانيتين
    reportProgress($book, ['pages' => range(1, 50)])->assertOk()->assertExactJson(['pages' => [1, 2, 3]]);
    reportProgress($book, ['pages' => range(1, 50)])->assertOk()->assertExactJson(['pages' => [1, 2, 3]]);
    expect(progressOf($this->reader, $book)->pages_count)->toBe(RecordLibraryProgress::FIRST_REPORT_PAGES);

    $this->travel(10)->seconds();
    reportProgress($book, ['pages' => range(1, 50)])->assertOk();
    expect(progressOf($this->reader, $book)->pages_count)->toBe(8)
        ->and(progressOf($this->reader, $book)->completed_at)->toBeNull();

    // والرصيد بعد انقطاع طويل خمسون صفحة لا أكثر
    $this->travel(1)->hours();
    reportProgress($book, ['pages' => range(51, 100)])->assertOk();
    reportProgress($book, ['pages' => range(1, 50)])->assertOk();
    expect(progressOf($this->reader, $book)->pages_count)->toBe(58);
});

it('shares the page and seconds budgets across items, so parallel reports do not multiply them', function () {
    $first = readingBook(50);
    $second = readingBook(50, ['slug' => 'second-book']);
    $audio = legacyAudio();
    $other = LibraryItem::factory()->audio()->create(['slug' => 'second-audio']);
    $this->actingAs($this->reader);

    reportProgress($first, ['pages' => [1, 2, 3]]);
    reportProgress($second, ['pages' => [1, 2, 3]]);
    expect(progressOf($this->reader, $first)->pages_count + progressOf($this->reader, $second)->pages_count)->toBe(3);

    reportProgress($audio);
    reportProgress($other);
    $this->travel(20)->seconds();
    reportProgress($audio, ['seconds' => 20]);
    reportProgress($other, ['seconds' => 20]);
    expect(progressOf($this->reader, $audio)->seconds_consumed + progressOf($this->reader, $other)->seconds_consumed)->toBeLessThanOrEqual(25);
});

it('rejects a page beyond the book page count, a fractional page, pages for audio and seconds for a book', function () {
    $book = readingBook(5);
    $audio = legacyAudio();
    $this->actingAs($this->reader);

    reportProgress($book, ['pages' => [6]])->assertJsonValidationErrors('pages.0');
    reportProgress($book, ['page' => 6])->assertJsonValidationErrors('page');
    reportProgress($book, ['pages' => [0]])->assertJsonValidationErrors('pages.0');
    reportProgress($book, ['pages' => ['2.5']])->assertJsonValidationErrors('pages.0');
    reportProgress($book, ['pages' => range(1, 51)])->assertJsonValidationErrors('pages');
    reportProgress($book, ['seconds' => 10])->assertJsonValidationErrors('seconds');
    reportProgress($audio, ['pages' => [1]])->assertJsonValidationErrors('pages');

    expect(LibraryProgress::query()->count())->toBe(0);
});

it('credits listening seconds only up to the wall-clock time, rejects a report over two minutes, and never lets the slack add up', function () {
    $audio = legacyAudio();
    $this->actingAs($this->reader);

    reportProgress($audio)->assertOk();
    $this->travel(20)->seconds();
    reportProgress($audio, ['seconds' => 20, 'position' => 20, 'duration' => 300])->assertOk();

    $progress = progressOf($this->reader, $audio);
    expect($progress->seconds_consumed)->toBe(20)->and($progress->last_position)->toBe(20)->and($progress->duration_seconds)->toBe(300);

    // عشر ثوانٍ مضت، وستون في البلاغ: لا يُحتسب إلا ما مضى مع الفسحة
    $this->travel(10)->seconds();
    reportProgress($audio, ['seconds' => 60])->assertOk();
    expect(progressOf($this->reader, $audio)->seconds_consumed)->toBe(35);

    reportProgress($audio, ['seconds' => 121])->assertJsonValidationErrors('seconds');
    reportProgress($audio, ['position' => 400, 'duration' => 300])->assertJsonValidationErrors('position');
    reportProgress($audio, ['duration' => 99999])->assertJsonValidationErrors('duration');

    // اثنا عشر بلاغاً كل خمس ثوانٍ لا تأخذ أكثر من ستين ثانية مع فسحة واحدة
    $before = progressOf($this->reader, $audio)->seconds_consumed;
    foreach (range(1, 11) as $ignored) {
        $this->travel(5)->seconds();
        reportProgress($audio, ['seconds' => 10])->assertOk();
    }

    expect(progressOf($this->reader, $audio)->seconds_consumed - $before)->toBeLessThanOrEqual(55 + RecordLibraryProgress::SECONDS_SLACK);
});

it('credits a first report with seconds only its small slack', function () {
    $audio = legacyAudio();
    $this->actingAs($this->reader);

    reportProgress($audio, ['seconds' => 60])->assertOk();
    expect(progressOf($this->reader, $audio)->seconds_consumed)->toBe(RecordLibraryProgress::SECONDS_SLACK);
});

// قبول ملاحظات 4 نوفمبر (5 نوفمبر 2026): على اتصال بطيء قُبل 413 من 466 ثانية لاستماع كامل. كان البلاغ المتأخر يضع ثوانيه منتهية
// عند وصوله ويقدّم ساعة الحساب إلى «الآن»، فيأكل من ثواني البلاغ الذي بعده
it('credits a late report its real seconds, so a slow connection does not lose the next report', function () {
    $audio = legacyAudio();
    $this->actingAs($this->reader);
    $start = now()->toImmutable();

    reportProgress($audio)->assertOk();
    // عشرون ثانية أُرسلت عند 20 ووصلت عند 35، ثم أربعون (تخطى المتصفح الإرسال عند 40 لأن سابقه لم يرجع) أُرسلت عند 60 ووصلت عند 62
    $this->travelTo($start->addSeconds(35));
    reportProgress($audio, ['seconds' => 20, 'position' => 20, 'duration' => 466])->assertOk();
    $this->travelTo($start->addSeconds(62));
    reportProgress($audio, ['seconds' => 40, 'position' => 60, 'duration' => 466])->assertOk();

    expect(progressOf($this->reader, $audio)->seconds_consumed)->toBe(60);
});

it('credits a whole listen on a slow connection with late and skipped reports', function () {
    $audio = legacyAudio();
    $this->actingAs($this->reader);
    $start = now()->toImmutable();
    reportProgress($audio)->assertOk();

    // المتصفح يرسل كل عشرين ثانية ما تجمّع ما لم يكن سابقه عالقاً، والطلب يصل بعد 1 إلى 25 ثانية (النفق في التجربة: 5 إلى 25)
    $delays = [3, 25, 2, 18, 9, 24, 1, 22, 5];
    $length = 466;
    $sent = 0;
    $pendingUntil = 0;
    $report = 0;

    foreach ([...range(20, $length, 20), $length] as $tick) {
        if ($tick < $pendingUntil && $tick !== $length) {
            continue;
        }

        $arrives = max($tick + $delays[$report++ % count($delays)], $pendingUntil);
        $this->travelTo($start->addSeconds($arrives));
        reportProgress($audio, ['seconds' => $tick - $sent, 'position' => $tick, 'duration' => $length])->assertOk();
        $sent = $tick;
        $pendingUntil = $arrives;
    }

    $progress = progressOf($this->reader, $audio);
    expect($sent)->toBe($length)
        ->and($progress->seconds_consumed)->toBe($length)
        ->and($progress->last_position)->toBe($length);
});

it('still caps forged seconds at the server clock: never more than the real time since the clock started with one slack', function () {
    $audio = legacyAudio();
    $this->actingAs($this->reader);
    $start = now()->toImmutable();
    reportProgress($audio)->assertOk();

    // بلاغ كل عشر ثوانٍ يدّعي دقيقتين، عشر دقائق
    foreach (range(10, 600, 10) as $second) {
        $this->travelTo($start->addSeconds($second));
        reportProgress($audio, ['seconds' => RecordLibraryProgress::MAX_SECONDS_PER_REPORT])->assertOk();
    }

    expect(progressOf($this->reader, $audio)->seconds_consumed)->toBeLessThanOrEqual(600 + RecordLibraryProgress::SECONDS_SLACK);

    // وبعد ساعة بلا تشغيل: دفعة بلاغات مزورة في خمس ثوانٍ لا تأخذ إلا بلاغاً واحداً وتأخره المحتمل والفسحة، ولا يتجاوز المجموع الوقت الفعلي
    $before = progressOf($this->reader, $audio)->seconds_consumed;
    foreach (range(0, 4) as $second) {
        $this->travelTo($start->addSeconds(4200 + $second));
        reportProgress($audio, ['seconds' => RecordLibraryProgress::MAX_SECONDS_PER_REPORT])->assertOk();
    }

    $total = progressOf($this->reader, $audio)->seconds_consumed;
    expect($total - $before)->toBeLessThanOrEqual(RecordLibraryProgress::MAX_SECONDS_PER_REPORT + RecordLibraryProgress::MAX_REPORT_DELAY + 4 + RecordLibraryProgress::SECONDS_SLACK)
        ->and($total)->toBeLessThanOrEqual(4204 + RecordLibraryProgress::SECONDS_SLACK);
});

// ملاحظات المدير التنفيذي، ليلة 4 نوفمبر 2026: شاهد مقطع يوتيوب كاملاً فلم تُسجَّل مشاهدته، لأن يوتيوب كان علامة «فتحته» وحدها. ومشغّله
// يبلّغ الآن تشغيله عبر postMessage (library-progress.js)، فتُقبل الثواني والموضع بساعة الخادم كالملف
it('takes playback seconds for a YouTube video and YouTube audio, and only an opened mark for a downloadable book', function () {
    $video = LibraryItem::factory()->video()->create(['slug' => 'reading-video']);
    $youtubeAudio = LibraryItem::factory()->state(['section' => LibrarySection::Audio, 'video_url' => 'https://www.youtube.com/watch?v=abcdefghijk', 'file' => null])->create(['slug' => 'youtube-audio']);
    $downloadable = LibraryItem::factory()->create(['slug' => 'reading-downloadable']);
    $this->actingAs($this->reader);

    expect($video->youtubeId())->not->toBeNull()->and($video->tracksPlayback())->toBeTrue()->and($youtubeAudio->tracksPlayback())->toBeTrue();

    reportProgress($video)->assertOk();
    reportProgress($youtubeAudio)->assertOk();
    reportProgress($downloadable)->assertOk();

    $this->travel(40)->seconds();
    reportProgress($video, ['seconds' => 30, 'position' => 30, 'duration' => 213])->assertOk();
    reportProgress($youtubeAudio, ['seconds' => 5, 'position' => 5])->assertOk();
    reportProgress($downloadable, ['pages' => [1]])->assertJsonValidationErrors('pages');
    // والموضع لا يتجاوز المدة المرسلة معه، والثواني بحدها
    reportProgress($video, ['position' => 300, 'duration' => 213])->assertJsonValidationErrors('position');
    reportProgress($video, ['seconds' => RecordLibraryProgress::MAX_SECONDS_PER_REPORT + 1])->assertJsonValidationErrors('seconds');

    $watched = progressOf($this->reader, $video);
    expect($watched->seconds_consumed)->toBe(30)
        ->and($watched->last_position)->toBe(30)
        ->and($watched->duration_seconds)->toBe(213)
        // والثانية للحساب كله لا لكل عنصر: الصوت بعد المقطع في الساعة نفسها لا يُحتسب منه إلا ما بقي منها مع الفسحة
        ->and(progressOf($this->reader, $youtubeAudio)->seconds_consumed)->toBeLessThanOrEqual(5)
        ->and(progressOf($this->reader, $downloadable)->pages_count)->toBe(0);

    // و«قراءاتي» تعرض موضع المشاهدة ومدتها، وتُحسب في دقائق المشاهدة
    $this->get(route('account.my-reading'))->assertOk()
        ->assertSee(__('reading.status.watched', ['position' => '0:30', 'duration' => '3:33']));
});

// والإضافة (المشرف، 01:14): «استمعت لمقطع كامل» — الاستماع الكامل والمشاهدة الكاملة يُسجّلان في «قراءاتي» و«البوابة بالأرقام»
it('records a full YouTube listen and a full YouTube watch in my reading totals and the portal numbers', function () {
    $audio = LibraryItem::factory()->state(['section' => LibrarySection::Audio, 'video_url' => 'https://www.youtube.com/watch?v=abcdefghijk', 'file' => null])->create(['slug' => 'full-listen']);
    $video = LibraryItem::factory()->video()->create(['slug' => 'full-watch']);
    $this->actingAs($this->reader);

    // كما يبلّغ المشغّل: «فتحته» عند ظهوره، ثم كل عشرين ثانية ما شُغّل منها، حتى نهاية مقطع من دقيقتين
    foreach ([$audio, $video] as $item) {
        reportProgress($item)->assertOk();

        foreach (range(1, 6) as $step) {
            $this->travel(20)->seconds();
            reportProgress($item, ['seconds' => 20, 'position' => $step * 20, 'duration' => 120])->assertOk();
        }
    }

    expect(progressOf($this->reader, $audio)->seconds_consumed)->toBe(120)
        ->and(progressOf($this->reader, $video)->seconds_consumed)->toBe(120)
        ->and(LibraryProgress::totals($this->reader))->toMatchArray(['listened_minutes' => 2, 'watched_minutes' => 2])
        ->and(HomePortalStats::compute()['media_views'])->toBe(2);

    $this->get(route('account.my-reading'))->assertOk()
        ->assertSee(__('reading.status.listened', ['position' => '2:00', 'duration' => '2:00']))
        ->assertSee(__('reading.status.watched', ['position' => '2:00', 'duration' => '2:00']));
});

it('refuses guests, unpublished, deleted or mismatched items, and inactive or deleted accounts', function () {
    $book = readingBook(5);

    reportProgress($book, ['pages' => [1]])->assertUnauthorized();

    $this->actingAs($this->reader);
    $hidden = LibraryItem::factory()->unpublished()->create(['slug' => 'hidden-book', 'is_downloadable' => false]);
    reportProgress($hidden)->assertNotFound();
    $this->postJson(route('library.progress', ['research', $book->slug]))->assertNotFound();

    $deleted = readingBook(5, ['slug' => 'deleted-book']);
    $deleted->delete();
    reportProgress($deleted)->assertNotFound();

    // الحساب الموقوف يُخرج قبل الطلب، والمخفى ترفضه السياسة، والإجراء يرفضهما ولو استُدعي مباشرة
    $inactive = User::factory()->create(['is_active' => false]);
    $anonymized = User::factory()->create(['anonymized_at' => now()]);
    expect($inactive->can('create', [LibraryProgress::class, $book]))->toBeFalse()
        ->and($anonymized->can('create', [LibraryProgress::class, $book]))->toBeFalse()
        ->and(fn () => app(RecordLibraryProgress::class)->handle($anonymized, $book, []))->toThrow(AuthorizationException::class);
    $this->actingAs($inactive);
    reportProgress($book)->assertRedirect();

    expect(LibraryProgress::query()->count())->toBe(0);
});

it('throttles reports per account, without blocking another account', function () {
    $book = readingBook(5);
    $this->actingAs($this->reader);

    foreach (range(1, 12) as $ignored) {
        reportProgress($book)->assertOk();
    }

    reportProgress($book)->assertTooManyRequests();
    $this->actingAs(User::factory()->create());
    reportProgress($book)->assertOk();
});

it('keeps the cached item page free of personal data, with the report endpoint for the scripts', function () {
    $book = readingBook(5);
    legacyAudio();
    LibraryItem::factory()->video()->create(['slug' => 'reading-video']);
    $downloadable = LibraryItem::factory()->create(['slug' => 'reading-downloadable']);
    Storage::disk('local')->put((string) $downloadable->file, pdfContents());

    $this->get('/library/books/reading-book')->assertOk()
        ->assertSee('data-progress="'.route('library.progress', ['books', 'reading-book']).'"', false);
    $this->get('/library/audio/reading-audio')->assertOk()->assertSee('data-library-media', false);
    $this->get('/library/videos/reading-video')->assertOk()->assertSee('data-progress="'.route('library.progress', ['videos', 'reading-video']).'"', false);
    $this->get('/library/books/reading-downloadable')->assertOk()->assertSee('data-progress-open=', false);

    $this->actingAs($this->reader);
    reportProgress($book, ['pages' => [1], 'page' => 1])->assertOk();
    $this->get('/library/books/reading-book')->assertOk()->assertDontSee($this->reader->name)->assertDontSee('resume');
});

it('shows my reading on the dashboard and its page, to the owner only, with continue links from the saved point', function () {
    $book = readingBook(5);
    $audio = legacyAudio();
    $this->actingAs($this->reader);
    reportProgress($book, ['pages' => [1, 2], 'page' => 2]);
    reportProgress($audio);
    $this->travel(20)->seconds();
    reportProgress($audio, ['seconds' => 20, 'position' => 75, 'duration' => 300]);

    $this->get('/account')->assertOk()
        ->assertSee(__('reading.title'))
        ->assertSee('كتاب القراءة')
        ->assertSee('مادة صوتية للاستماع')
        ->assertSee(trans_choice('reading.status.pages', 2, ['page' => 2, 'total' => 5, 'read' => 2]))
        ->assertSee(__('reading.status.listened', ['position' => '1:15', 'duration' => '5:00']))
        ->assertSee(route('account.my-reading'));

    $this->get('/account/my-reading')->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee(__('reading.sections.books'))
        ->assertSee(__('reading.sections.audio'))
        ->assertSee(e(route('library.show', ['books', 'reading-book', 'resume' => 2])), false)
        ->assertSee(e(route('library.show', ['audio', 'reading-audio', 'resume' => 75])), false)
        ->assertSee(__('reading.actions.continue_reading'))
        ->assertSee(__('reading.actions.continue_listening'))
        ->assertSee(__('reading.clear'));

    // غيره لا يرى شيئاً من قراءاته، ولو كان مديراً، ولا معرّف في الرابط يُغيَّر
    $other = userWithRole(RoleName::TechnicalAdmin);
    $this->actingAs($other)->get('/account/my-reading')->assertOk()->assertDontSee('كتاب القراءة')->assertSee(__('reading.empty'));
    // والكتاب نفسه منشور فقد يظهر في شريط «جديد مكتبة إعجاز» العام (بغلاف أو بلا غلاف منذ ملاحظات المدير في 1 نوفمبر)، فالفحص
    // لقراءاته هو: لا تقدّم ولا رابط استئناف في لوحة غيره
    $this->actingAs($other)->get('/account')->assertOk()
        ->assertDontSee(trans_choice('reading.status.pages', 2, ['page' => 2, 'total' => 5, 'read' => 2]))
        ->assertDontSee(e(route('library.show', ['books', 'reading-book', 'resume' => 2])), false);
    expect($other->can('view', progressOf($this->reader, $book)))->toBeFalse()
        ->and($this->reader->can('view', progressOf($this->reader, $book)))->toBeTrue()
        ->and($other->can('viewAny', LibraryProgress::class))->toBeFalse();
});

it('lets the owner clear their whole reading history, and nobody else\'s', function () {
    $book = readingBook(5);
    $this->actingAs($this->reader);
    reportProgress($book, ['pages' => [1], 'page' => 1]);
    $other = User::factory()->create();
    LibraryProgress::query()->forceCreate(['user_id' => $other->id, 'library_item_id' => $book->id, 'pages_count' => 1, 'last_reported_at' => now()]);

    $this->post(route('account.my-reading.clear'))->assertRedirect(route('account.my-reading'))->assertSessionHas('status', __('reading.cleared'));

    expect(progressOf($this->reader, $book))->toBeNull()->and(progressOf($other, $book))->not->toBeNull();
    $this->post('/logout');
    $this->post(route('account.my-reading.clear'))->assertRedirect('/login');
    expect(progressOf($other, $book))->not->toBeNull();
});

it('hides items that were unpublished after reading, and invites to the library when there is nothing', function () {
    $book = readingBook(5);
    $this->actingAs($this->reader);

    $this->get('/account')->assertOk()->assertSee(__('reading.empty'))->assertSee(route('library.index'));

    reportProgress($book, ['pages' => [1], 'page' => 1]);
    $book->update(['is_published' => false]);

    $this->get('/account/my-reading')->assertOk()->assertDontSee('كتاب القراءة')->assertSee(__('reading.empty'));
    $this->post('/logout');
    $this->get('/account/my-reading')->assertRedirect('/login');
});

it('exposes portal totals without personal data', function () {
    $book = readingBook(5);
    $audio = legacyAudio();
    $video = LibraryItem::factory()->state(['section' => LibrarySection::Videos, 'video_url' => 'https://media.example.test/a.mp4', 'file' => null])->create(['slug' => 'file-video']);
    $this->actingAs($this->reader);
    reportProgress($book, ['pages' => [1, 2, 3]]);
    reportProgress($audio);
    reportProgress($video);
    $this->travel(100)->seconds();
    reportProgress($audio, ['seconds' => 100]);
    $this->travel(100)->seconds();
    reportProgress($video, ['seconds' => 90, 'position' => 90, 'duration' => 600]);

    $this->actingAs(User::factory()->create());
    reportProgress($book, ['pages' => [1]]);

    expect(LibraryProgress::totals())->toBe(['pages_read' => 4, 'listened_minutes' => 1, 'watched_minutes' => 1, 'readers' => 2])
        ->and(LibraryProgress::totals($this->reader)['pages_read'])->toBe(3);
});

it('deletes the reading of an account on its deletion, purges leftovers, and includes it in the data copy', function () {
    $book = readingBook(5);
    $this->actingAs($this->reader);
    reportProgress($book, ['pages' => [1, 2], 'page' => 2]);
    $other = User::factory()->create();
    LibraryProgress::query()->forceCreate(['user_id' => $other->id, 'library_item_id' => $book->id, 'pages_count' => 1, 'last_reported_at' => now()]);

    $copy = app(ExportUserData::class)->handle($this->reader);
    expect($copy['library_progress'])->toHaveCount(1)
        ->and($copy['library_progress'][0])->toMatchArray(['item' => 'كتاب القراءة', 'pages_seen' => '1-2', 'total_pages' => 5, 'last_page' => 2]);

    $this->reader->forceFill(['deletion_requested_at' => now()])->save();
    app(AnonymizeUser::class)->handle(userWithRole(RoleName::TechnicalAdmin), $this->reader);
    expect(progressOf($this->reader, $book))->toBeNull()->and(progressOf($other, $book))->not->toBeNull();

    // صف عاد لحساب مخفى (استعادة من نسخة) يحذفه أمر الاحتفاظ ولو لم تُضبط المدة
    LibraryProgress::query()->forceCreate(['user_id' => $this->reader->id, 'library_item_id' => $book->id, 'pages_count' => 2, 'last_reported_at' => now()]);
    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();
    expect(progressOf($this->reader, $book))->toBeNull()->and(progressOf($other, $book))->not->toBeNull();
});

/**
 * مهمة العد كما يشغّلها عامل طابور library.
 */
function countPages(LibraryItem $item): void
{
    app()->call([new CountLibraryPages($item->id), 'handle']);
}

it('queues the page count once on its own queue for a book that cannot be counted, until its file changes', function () {
    Queue::fake();
    $book = LibraryItem::factory()->create(['slug' => 'uncountable', 'is_downloadable' => false]);
    Storage::disk('local')->put((string) $book->file, 'not a pdf at all');
    $this->actingAs($this->reader);

    reportProgress($book, ['pages' => [1], 'page' => 1])->assertOk();
    Queue::assertPushedOn(CountLibraryPages::QUEUE, CountLibraryPages::class);
    expect((new CountLibraryPages($book->id))->timeout)->toBe(120);

    // العامل يحاول ويتعذر العد: تُحفظ المحاولة، فلا يعيدها بلاغ تالٍ من أي حساب
    countPages($book);
    expect($book->fresh()->page_count)->toBeNull()->and($book->fresh()->page_count_checked_at)->not->toBeNull();

    $this->travel(20)->seconds();
    reportProgress($book, ['pages' => [2], 'page' => 2])->assertOk();
    $this->actingAs(User::factory()->create());
    reportProgress($book, ['pages' => [1]])->assertOk();
    Queue::assertPushedTimes(CountLibraryPages::class, 1);

    // ملف جديد يعيد المحاولة
    app(SaveLibraryItem::class)->handle(userWithRole(RoleName::ContentEditor), $book->fresh(), ['file' => storedPrivateFile(LibraryItem::FILE_DIRECTORY, readingPdf(3))]);
    expect($book->fresh()->page_count_checked_at)->toBeNull();
    Queue::assertPushedTimes(CountLibraryPages::class, 2);
});

it('clips pages stored above the real count once it is known, so the book neither completes nor counts more than its pages', function () {
    Queue::fake();
    $book = LibraryItem::factory()->create(['slug' => 'late-count', 'is_downloadable' => false]);
    Storage::disk('local')->put((string) $book->file, readingPdf(5));
    $this->actingAs($this->reader);

    // قبل العد يُقبل حتى 10,000
    reportProgress($book, ['pages' => [1, 2, 3], 'page' => 3])->assertOk();
    $this->travel(20)->seconds();
    reportProgress($book, ['pages' => [8, 9, 10, 11, 12], 'page' => 12])->assertOk();
    expect(progressOf($this->reader, $book)->pages_count)->toBe(8);

    // يُعرف العدد: يُقصّ المحفوظ في المهمة نفسها
    countPages($book);
    $progress = progressOf($this->reader, $book);
    expect($progress->pages_count)->toBe(3)->and($progress->pages_seen)->toBe('1-3')->and($progress->total_pages)->toBe(5)
        ->and($progress->last_page)->toBe(5)->and($progress->completed_at)->toBeNull();

    // وكتاب استُعيد بتقدّم قديم فوق عدده يُقصّ في البلاغ التالي أيضاً، ولا يكتمل بصفحات ليست فيه
    $progress->forceFill(['pages_seen' => '1-3,6-40', 'pages_count' => 38, 'completed_at' => now()])->save();
    $this->travel(20)->seconds();
    reportProgress($book, ['pages' => [4]])->assertOk();
    $progress = progressOf($this->reader, $book);
    expect($progress->pages_count)->toBe(4)->and($progress->pages_seen)->toBe('1-4')->and($progress->completed_at)->toBeNull();

    $this->travel(20)->seconds();
    reportProgress($book, ['pages' => [5]])->assertOk();
    expect(progressOf($this->reader, $book)->completed_at)->not->toBeNull();
});

it('falls back to counting page objects when a file has more than the cap of page-tree nodes', function () {
    $nodes = str_repeat("<< /Type /Pages /Count 999 >>\n", LibraryPageCount::MAX_PAGES_NODES + 1);
    $pages = str_repeat("<< /Type /Page >>\n", 4);

    expect(LibraryPageCount::count("%PDF-1.4\n".$nodes.$pages))->toBe(4)
        ->and(LibraryPageCount::count("%PDF-1.4\n<< /Type /Pages /Count 7 >>\n".$pages))->toBe(7);
});

it('lets the owner turn off saving: reports are accepted silently and nothing is stored, and the browser is told not to send them', function () {
    $book = readingBook(5);
    $this->actingAs($this->reader);

    // الافتراضي: الحفظ تلقائي، ولا وسم إيقاف في الرأس
    $this->get('/library/books/reading-book')->assertOk()->assertDontSee('name="reading-tracking"', false);
    $this->get('/account/my-reading')->assertOk()->assertSee('data-reading-tracking-note="on"', false)->assertSee(__('reading.tracking.on_note'));
    $this->get('/account/profile')->assertOk()->assertSee(__('reading.tracking.label'))->assertSee(route('account.my-reading.tracking'));

    $this->post(route('account.my-reading.tracking'), ['disabled' => '1'])->assertRedirect(route('account.profile'))->assertSessionHas('status', __('reading.tracking.disabled'));
    expect($this->reader->fresh()->reading_tracking_disabled_at)->not->toBeNull();

    // صفحة المكتبة المخزنة نفسها للجميع، والإيقاف في الرأس غير المخزن لهذا الحساب وحده
    $this->get('/library/books/reading-book')->assertOk()->assertSee('<meta name="reading-tracking" content="off">', false);
    $this->get('/account/my-reading')->assertOk()->assertSee('data-reading-tracking-note="off"', false);
    reportProgress($book, ['pages' => [1, 2], 'page' => 2])->assertOk()->assertExactJson(['pages' => [1, 2]]);
    expect(LibraryProgress::query()->count())->toBe(0);

    // حساب آخر يبقى الحفظ له
    $this->actingAs($other = User::factory()->create());
    $this->get('/library/books/reading-book')->assertOk()->assertDontSee('name="reading-tracking"', false);
    reportProgress($book, ['pages' => [1]])->assertOk();
    expect(progressOf($other, $book))->not->toBeNull();

    // وإعادة الحفظ
    $this->actingAs($this->reader->fresh());
    $this->post(route('account.my-reading.tracking'), ['disabled' => '0'])->assertSessionHas('status', __('reading.tracking.enabled'));
    reportProgress($book, ['pages' => [1]])->assertOk();
    expect(progressOf($this->reader, $book))->not->toBeNull();

    $this->post(route('account.my-reading.tracking'), ['disabled' => 'maybe'])->assertSessionHasErrors('disabled');
    $this->post('/logout');
    $this->post(route('account.my-reading.tracking'), ['disabled' => '1'])->assertRedirect('/login');
});

it('leaves unpublished and deleted items out of the portal totals', function () {
    $book = readingBook(5);
    $audio = legacyAudio();
    $this->actingAs($this->reader);
    reportProgress($book, ['pages' => [1, 2, 3]]);
    reportProgress($audio);
    $this->travel(100)->seconds();
    reportProgress($audio, ['seconds' => 100]);

    expect(LibraryProgress::totals())->toBe(['pages_read' => 3, 'listened_minutes' => 1, 'watched_minutes' => 0, 'readers' => 1]);

    $book->update(['is_published' => false]);
    $audio->delete();

    expect(LibraryProgress::totals())->toBe(['pages_read' => 0, 'listened_minutes' => 0, 'watched_minutes' => 0, 'readers' => 0])
        ->and(LibraryProgress::totals($this->reader)['pages_read'])->toBe(0);
});

it('moves the account clocks only once the progress is committed, so a rolled-back report spends no budget', function () {
    $book = readingBook(5);
    $audio = legacyAudio();
    $pages = 'library-progress:'.$this->reader->id.':pages';
    $seconds = 'library-progress:'.$this->reader->id.':seconds';
    $record = app(RecordLibraryProgress::class);

    DB::beginTransaction();
    $record->handle($this->reader, $book, ['pages' => [1, 2, 3]]);
    $record->handle($this->reader, $audio, ['seconds' => 10]);
    DB::rollBack();

    expect(Cache::has($pages))->toBeFalse()
        ->and(Cache::has($seconds))->toBeFalse()
        ->and(progressOf($this->reader, $book))->toBeNull();

    $record->handle($this->reader, $book, ['pages' => [1, 2, 3]]);
    $record->handle($this->reader, $audio, ['seconds' => 10]);

    expect(Cache::has($pages))->toBeTrue()
        ->and(Cache::has($seconds))->toBeTrue()
        ->and(progressOf($this->reader, $book)->pages_count)->toBe(3);
});

// ولا تُنفخ «مشاهدات المقاطع» بثانية لكل مقطع: تُعدّ من عشر ثوانٍ تشغيل فأكثر (مراجعة المشرف على #182، L2)
it('counts a portal media view only from ten seconds of playback', function () {
    $short = LibraryItem::factory()->video()->create(['slug' => 'short-view']);
    $long = LibraryItem::factory()->video()->create(['slug' => 'long-view']);
    foreach ([[$short, HomePortalStats::MIN_VIEW_SECONDS - 1], [$long, HomePortalStats::MIN_VIEW_SECONDS]] as [$item, $seconds]) {
        LibraryProgress::query()->forceCreate(['user_id' => $this->reader->id, 'library_item_id' => $item->id, 'last_reported_at' => now(), 'seconds_consumed' => $seconds]);
    }

    expect(HomePortalStats::compute()['media_views'])->toBe(1);
});

// قبول ملاحظات 4 نوفمبر (5 نوفمبر 2026): «قرأت 4 صفحة» والصواب «4 صفحات»، بصيغ العدد العربية
it('says the pages read with the Arabic count forms', function (int $read, string $phrase) {
    $book = LibraryItem::factory()->create(['slug' => 'count-book', 'is_downloadable' => false]);
    LibraryProgress::query()->forceCreate(['user_id' => $this->reader->id, 'library_item_id' => $book->id, 'last_reported_at' => now(),
        'pages_count' => $read, 'last_page' => $read, 'total_pages' => 285]);

    $this->actingAs($this->reader)->get('/account/my-reading')->assertOk()
        ->assertSee('وصلت إلى الصفحة '.number_format($read).' من 285 — '.$phrase)
        ->assertDontSee('صفحة|');
})->with([
    'one' => [1, 'قرأت صفحة واحدة'],
    'two' => [2, 'قرأت صفحتين'],
    'three' => [3, 'قرأت 3 صفحات'],
    'four' => [4, 'قرأت 4 صفحات'],
    'ten' => [10, 'قرأت 10 صفحات'],
    'eleven' => [11, 'قرأت 11 صفحة'],
    'ninety-nine' => [99, 'قرأت 99 صفحة'],
    'hundred' => [100, 'قرأت 100 صفحة'],
    'hundred and three' => [103, 'قرأت 103 صفحات'],
]);

it('uses the count forms for a book of unknown length and a completed book too', function () {
    $open = LibraryItem::factory()->create(['slug' => 'open-book', 'is_downloadable' => false]);
    $done = LibraryItem::factory()->create(['slug' => 'done-book', 'is_downloadable' => false]);
    $pair = LibraryItem::factory()->create(['slug' => 'pair-book', 'is_downloadable' => false]);
    foreach ([[$open, 4, null, null], [$done, 4, 4, now()], [$pair, 2, 2, now()]] as [$item, $read, $total, $completed]) {
        LibraryProgress::query()->forceCreate(['user_id' => $this->reader->id, 'library_item_id' => $item->id, 'last_reported_at' => now(),
            'pages_count' => $read, 'last_page' => $read, 'total_pages' => $total, 'completed_at' => $completed]);
    }

    $this->actingAs($this->reader)->get('/account/my-reading')->assertOk()
        ->assertSee('وصلت إلى الصفحة 4 — قرأت 4 صفحات')
        ->assertSee('مكتمل — قرأت 4 من 4 صفحات')
        ->assertSee('مكتمل — قرأت الصفحتين')
        ->assertDontSee('صفحة|');
});

// قبول ملاحظات 4 نوفمبر: زرا «أكمل المشاهدة» و«أكمل الاستماع» بقيا بعد إنهاء المقطع، ورابطهما يبدأ من النهاية
it('swaps the continue button of a video or audio played to its end for one from the start, and keeps it before the end', function () {
    $finishedVideo = LibraryItem::factory()->video()->create(['slug' => 'finished-video', 'title' => 'مقطع شوهد كاملاً']);
    $finishedAudio = LibraryItem::factory()->audio()->create(['slug' => 'finished-audio', 'title' => 'صوت سُمع كاملاً']);
    $halfAudio = LibraryItem::factory()->audio()->create(['slug' => 'half-audio', 'title' => 'صوت سُمع نصفه']);
    foreach ([[$finishedVideo, 175, 175], [$finishedAudio, 464, 465], [$halfAudio, 200, 465]] as [$item, $position, $duration]) {
        LibraryProgress::query()->forceCreate(['user_id' => $this->reader->id, 'library_item_id' => $item->id, 'last_reported_at' => now(),
            'seconds_consumed' => $position, 'last_position' => $position, 'duration_seconds' => $duration]);
    }

    $html = (string) $this->actingAs($this->reader)->get('/account/my-reading')->assertOk()->getContent();
    $card = fn (string $title): string => (string) str($html)->after($title)->before('</li>');

    expect($card('مقطع شوهد كاملاً'))->toContain('شاهدت حتى 2:55 من 2:55')->not->toContain(__('reading.actions.continue_watching'))
        ->toContain(__('reading.actions.watch_again'))->toContain('href="'.e(route('library.show', ['video', 'finished-video'])).'"')->not->toContain('resume=')
        ->and($card('صوت سُمع كاملاً'))->not->toContain(__('reading.actions.continue_listening'))
        ->toContain(__('reading.actions.listen_again'))->toContain('href="'.e(route('library.show', ['audio', 'finished-audio'])).'"')->not->toContain('resume=')
        ->and($card('صوت سُمع نصفه'))->toContain('data-reading-action')->toContain(__('reading.actions.continue_listening'))
        ->toContain(e(route('library.show', ['audio', 'half-audio', 'resume' => 200])));
});
