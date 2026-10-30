<?php

use App\Models\Page;

/*
| مقدمة صفحة الخدمة من صفحة محتوى (Page::INTROS)، ونص فريق البوابة فيها (Page::is_sample). كان يحمل وسم «مثال تجريبي»
| للعامة (الاختبار الشامل 0.14)، ثم أُزيل الوسم من كل ما يراه الزائر قبل الإطلاق (قرار صفوان، 29 أكتوبر 2026)، ويبقى في
| اللوحة وحدها حتى تراجعه الجمعية.
*/

it('shows the intro of a sample page without any tag to the public', function (string $slug, string $url) {
    Page::factory()->create([
        'slug' => $slug,
        'is_sample' => true,
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>نص المقدمة المؤقت</p>']]],
    ]);

    $this->get($url)->assertOk()->assertSee('نص المقدمة المؤقت')->assertDontSee('مثال تجريبي');
})->with([
    'inquiries' => ['inquiries', '/contact'],
    'jobs' => ['jobs', '/services/jobs'],
    'volunteering' => ['volunteering', '/services/volunteering'],
    'reports' => ['reports', '/governance?category=annual-reports'],
]);

it('shows nothing of an unpublished sample intro', function () {
    Page::factory()->create([
        'slug' => 'inquiries',
        'is_sample' => true,
        'is_published' => false,
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>نص المقدمة المؤقت</p>']]],
    ]);

    $this->get('/contact')->assertOk()->assertDontSee('نص المقدمة المؤقت')->assertDontSee('مثال تجريبي');
});
