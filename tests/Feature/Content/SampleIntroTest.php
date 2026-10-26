<?php

use App\Models\Page;

/*
| مقدمة صفحة الخدمة من صفحة محتوى (Page::INTROS) تحمل وسم «مثال تجريبي» إن كانت الصفحة موسومة، كما في /pages/{slug}.
| وجده الاختبار الشامل 0.14 (docs/reviews/2026-10-26-الاختبار-الشامل-0.14.md): نصوص الاستفسارات والتوظيف والتطوع
| والتقارير المكتوبة من فريق البوابة تظهر في صفحات الخدمات بلا وسم متى نُشرت.
*/

it('tags a sample intro on the page that shows it, and drops the tag once the association text is approved', function (string $slug, string $url) {
    $page = Page::factory()->create([
        'slug' => $slug,
        'is_sample' => true,
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>نص المقدمة المؤقت</p>']]],
    ]);

    $this->get($url)->assertOk()->assertSee('نص المقدمة المؤقت')->assertSee(__('site.sample'));

    $page->update(['is_sample' => false]);

    $this->get($url)->assertOk()->assertSee('نص المقدمة المؤقت')->assertDontSee(__('site.sample'));
})->with([
    'inquiries' => ['inquiries', '/contact'],
    'jobs' => ['jobs', '/services/jobs'],
    'volunteering' => ['volunteering', '/services/volunteering'],
    'reports' => ['reports', '/governance?category=annual-reports'],
]);

it('shows nothing of an unpublished sample intro, not even its tag', function () {
    Page::factory()->create([
        'slug' => 'inquiries',
        'is_sample' => true,
        'is_published' => false,
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>نص المقدمة المؤقت</p>']]],
    ]);

    $this->get('/contact')->assertOk()->assertDontSee('نص المقدمة المؤقت')->assertDontSee(__('site.sample'));
});
