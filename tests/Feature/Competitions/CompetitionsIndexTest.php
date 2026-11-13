<?php

use App\Models\Competition;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// صفحة المسابقات (البند R15 من تدقيق القبول، 2 نوفمبر): التصنيف باستعلامات مستقلة قبل الترقيم، فالحالية
// والقادمة في الصفحة الأولى بلا ترقيم، والسابقة وحدها مرقّمة

it('keeps the current competition prominent on the first page even when many past ones exist', function () {
    // سابقات أكثر من صفحة، وكلها فُتحت بعد الحالية: الترتيب القديم بموعد الفتح كان يدفع الحالية إلى الصفحة الثانية
    foreach (range(1, 15) as $i) {
        Competition::factory()->create(['title' => 'مسابقة سابقة '.$i, 'opens_at' => now()->subDays(20 - $i), 'closes_at' => now()->subDays(19 - $i)]);
    }
    Competition::factory()->create(['title' => 'المسابقة الجارية', 'opens_at' => now()->subMonths(2), 'closes_at' => now()->addWeek()]);

    $html = (string) $this->get('/competitions')->assertOk()->getContent();

    expect(str($html)->after('data-competitions-current')->before('data-competitions-past')->toString())->toContain('المسابقة الجارية')
        // السابقة مرقّمة بأحدثها انتهاءً: 12 في الأولى والثلاث الأقدم في الثانية
        ->and(substr_count($html, 'data-competition-card="closed"'))->toBe(12)
        ->and($html)->toContain('مسابقة سابقة 15')->not->toContain('مسابقة سابقة 3<');

    $second = (string) $this->get('/competitions?page=2')->assertOk()->getContent();

    expect($second)->toContain('مسابقة سابقة 3')->toContain('مسابقة سابقة 1')
        ->not->toContain('data-competitions-current')->not->toContain('المسابقة الجارية')
        ->and(substr_count($second, 'data-competition-card="closed"'))->toBe(3);

    $this->get('/competitions?page=3')->assertNotFound();
});

it('lists upcoming competitions as «soon» without a registration button', function () {
    foreach (range(1, 14) as $i) {
        Competition::factory()->closed()->create();
    }
    Competition::factory()->create(['title' => 'مسابقة بعد شهر', 'opens_at' => now()->addMonth(), 'closes_at' => now()->addMonths(2)]);
    Competition::factory()->upcoming()->create(['title' => 'مسابقة غداً']);

    $html = (string) $this->get('/competitions')->assertOk()->getContent();
    $upcoming = str($html)->after('data-competitions-upcoming')->before('data-competitions-past')->toString();

    expect($upcoming)->toContain(__('competitions.statuses.upcoming'))
        ->and(substr_count($upcoming, 'data-competition-card="upcoming"'))->toBe(2)
        // الأقرب فتحاً أولاً
        ->and(strpos($upcoming, 'مسابقة غداً'))->toBeLessThan(strpos($upcoming, 'مسابقة بعد شهر'))
        // وقسم «المسابقة الحالية» ظاهر بحالته الفارغة بلا مسابقة مفتوحة (قرار صفوان، 11 نوفمبر 2026)
        ->and($html)->toContain('data-competitions-empty="current"')->not->toContain('/register"')
        ->not->toContain('entry/start');
});

it('archives past competitions with the approved results badge, and hides drafts from every section', function () {
    Competition::factory()->closed()->create(['title' => 'مسابقة معتمدة النتائج', 'results_approved_at' => now()]);
    Competition::factory()->closed()->create(['title' => 'مسابقة بلا نتائج']);
    Competition::factory()->unpublished()->create(['title' => 'مسودة جارية', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

    $html = (string) $this->get('/competitions')->assertOk()->getContent();
    $past = str($html)->after('data-competitions-past')->toString();

    expect($past)->toContain('مسابقة معتمدة النتائج')->toContain('مسابقة بلا نتائج')
        ->and(substr_count($past, 'data-results-approved'))->toBe(1)
        ->and($html)->not->toContain('مسودة جارية')->toContain('data-competitions-empty="current"');
});

it('keeps the three sections with their empty states when nothing is published', function () {
    Competition::factory()->unpublished()->create();

    // الأقسام الثلاثة ظاهرة بحالتها الفارغة (قرار صفوان، 11 نوفمبر 2026 — docs/decisions/2026-11-11-fix-show-empty-sections.md)
    $this->get('/competitions')->assertOk()
        ->assertSee(__('competitions.list.current_empty'))
        ->assertSee(__('competitions.list.upcoming_empty'))
        ->assertSee(__('competitions.list.past_empty'));
    $this->get('/competitions?page=2')->assertNotFound();
});

it('puts each competition in exactly one section at the exact boundaries of its status', function () {
    $this->travelTo(now()->startOfMinute());

    Competition::factory()->create(['title' => 'تفتح الآن', 'opens_at' => now(), 'closes_at' => now()->addDay()]);
    Competition::factory()->create(['title' => 'تغلق الآن', 'opens_at' => now()->subDay(), 'closes_at' => now()]);
    Competition::factory()->create(['title' => 'تفتح بعد ثانية', 'opens_at' => now()->addSecond(), 'closes_at' => now()->addDay()]);

    $html = (string) $this->get('/competitions')->assertOk()->getContent();
    $current = str($html)->after('data-competitions-current')->before('data-competitions-upcoming')->toString();
    $upcoming = str($html)->after('data-competitions-upcoming')->before('data-competitions-past')->toString();
    $past = str($html)->after('data-competitions-past')->toString();

    expect($current)->toContain('تفتح الآن')->not->toContain('تغلق الآن')->not->toContain('تفتح بعد ثانية')
        ->and($upcoming)->toContain('تفتح بعد ثانية')->not->toContain('تفتح الآن')->not->toContain('تغلق الآن')
        ->and($past)->toContain('تغلق الآن')->not->toContain('تفتح الآن')->not->toContain('تفتح بعد ثانية');

    foreach (Competition::all() as $competition) {
        expect(substr_count($html, 'href="'.route('competitions.show', $competition->slug).'"'))->toBe(1);
    }
});

it('relies on opens_at and closes_at being required, so no competition can fall outside every section', function () {
    // ملاحظة المشرف المنخفضة على #123: مسابقة بموعد فارغ لا تقع في أي قسم. والعمودان NOT NULL في القاعدة، فهذا حارس للقيد
    $competition = Competition::factory()->create();

    foreach (['opens_at', 'closes_at'] as $column) {
        expect(fn () => DB::transaction(fn () => DB::table('competitions')->whereKey($competition->id)->update([$column => null])))
            ->toThrow(QueryException::class);
    }
});
