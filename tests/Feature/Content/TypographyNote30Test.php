<?php

use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\NewsArticle;
use App\Models\Page;
use App\Models\Program;
use App\Models\User;

/*
| ملاحظة صفوان ٣٠، الجزء الثاني (3 نوفمبر 2026): طول سطر الفقرات الطويلة (.measure، نحو 68 حرفاً)، وقص عناوين البطاقات بسطرين،
| والبطاقات بلا خانة فارغة في طرف صفها الأخير (.tile-grid). والقياس الفعلي في المتصفح: tests/browser/note-30-pages.spec.mjs.
*/

/**
 * عنصر بصنف معين (أياً كان ترتيب أصنافه) يبدأ نصه بالنص المعطى.
 */
function elementWithClass(string $class, string $text): string
{
    return '/class="[^"]*\b'.preg_quote($class, '/').'\b[^"]*"[^>]*>\s*'.preg_quote($text, '/').'/u';
}

it('limits the line length of long text on content pages, the chairman word and the programme page', function () {
    Page::factory()->create([
        'slug' => 'note-30-page',
        'is_published' => true,
        'blocks' => [['type' => 'text', 'data' => ['heading' => 'عنوان', 'body' => '<p>نص طويل</p>']]],
    ]);
    Page::factory()->create(['slug' => Page::ABOUT, 'is_published' => true, 'blocks' => []]);
    $program = Program::factory()->create(['description' => 'وصف البرنامج']);

    // الحد على أبناء النص لا على حاويته (measure-flow)، فلا يضيق جدول أو صورة فيه
    $page = (string) $this->get('/pages/note-30-page')->assertOk()->getContent();
    expect($page)->toMatch('/class="[^"]*\bprose-ar\b[^"]*\bmeasure-flow\b/u')
        ->and($page)->not->toMatch('/class="[^"]*\bprose-ar\b[^"]*\bmeasure\b(?!-)/u')
        ->and($this->get('/about')->assertOk()->getContent())->toMatch('/data-chairman-card.*class="[^"]*\bmeasure\b/su')
        ->and($this->get(route('programs.show', $program->slug))->assertOk()->getContent())->toMatch(elementWithClass('measure', 'وصف البرنامج'));
});

it('clamps card titles at two lines so cards in a row keep one height', function () {
    NewsArticle::factory()->create(['title' => 'خبر بعنوان طويل', 'is_published' => true]);
    Initiative::factory()->create(['name' => 'مبادرة الاختبار', 'summary' => 'ملخص', 'is_published' => true]);
    Program::factory()->create(['title' => 'برنامج الاختبار']);
    $book = LibraryItem::factory()->create(['title' => 'كتاب الاختبار', 'is_published' => true]);

    expect($this->get(route('media.news.index'))->assertOk()->getContent())->toMatch(elementWithClass('line-clamp-2', 'خبر بعنوان طويل'))
        ->and($this->get(route('initiatives.index'))->assertOk()->getContent())->toMatch(elementWithClass('line-clamp-2', 'مبادرة الاختبار'))
        ->and($this->get(route('programs.index'))->assertOk()->getContent())->toMatch('/class="[^"]*\bline-clamp-2\b[^"]*">\s*<a[^>]*>برنامج الاختبار/u')
        ->and($this->get(route('library.section', $book->section->value))->assertOk()->getContent())->toMatch(elementWithClass('line-clamp-2', 'كتاب الاختبار'));
});

it('centres the incomplete last row of the service tiles', function () {
    expect($this->get('/services')->assertOk()->getContent())->toMatch('/<ul class="[^"]*\btile-grid\b/u');
});

it('lays the beneficiary cards in one column on mobile and two fixed columns from the tablet up (Safwan, #142)', function () {
    $list = (string) str($this->actingAs(User::factory()->create())->get('/account')->assertOk()->getContent())
        ->match('/<ul class="([^"]*)"[^>]*data-account-cards/u');

    expect($list)->toMatch('/(^|\s)grid(\s|$)/')
        ->and($list)->toMatch('/\bsm:grid-cols-2\b/')
        ->and($list)->not->toMatch('/grid-cols-3|tile-grid/');
});
