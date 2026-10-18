<?php

use App\Models\Page;
use App\Models\Survey;
use Database\Seeders\ContentSeeder;

/*
| ملاحظات التجربة الشاملة (18 أكتوبر 2026، الخلل 4): /privacy يعرض صفحة المحتوى privacy متى نُشرت وإلا 404،
| ورابط «سياسة الخصوصية» في التسجيل والتواصل والاستبيان والتذييل يظهر بنشرها. ونصها من الجمعية وحدها:
| البذرة مسودة بعلامة النقص (docs/open-questions.md — مانع للإطلاق).
*/

function privacyPage(bool $published = true): Page
{
    return Page::factory()->create(['slug' => Page::PRIVACY, 'title' => 'سياسة الخصوصية', 'is_published' => $published, 'blocks' => [
        ['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>نص السياسة للاختبار</p>']],
    ]]);
}

it('answers 404 at /privacy while there is no privacy page or it is a draft', function () {
    $this->get('/privacy')->assertNotFound();

    privacyPage(published: false);

    $this->get('/privacy')->assertNotFound();
});

it('shows the published privacy page at /privacy, and sends its slug address there', function () {
    privacyPage();

    $this->get('/privacy')->assertOk()
        ->assertSeeInOrder(['<h1', 'سياسة الخصوصية'], false)
        ->assertSee('نص السياسة للاختبار');

    $this->get('/pages/privacy')->assertStatus(301)->assertRedirect(route('privacy'));
});

it('hides the privacy link on registration, contact, the survey and the footer until the page is published', function (string $url) {
    $page = privacyPage(published: false);
    $link = 'href="'.route('privacy').'"';

    $this->get($url)->assertOk()->assertDontSee($link, false);

    $page->update(['is_published' => true]);

    $this->get($url)->assertOk()->assertSee($link, false);
})->with([
    'registration' => fn () => '/register',
    'contact' => fn () => '/contact',
    'survey' => fn () => '/surveys/'.Survey::factory()->withQuestions()->guests()->create()->token,
    'footer' => fn () => '/',
]);

it('links the registration consent to the policy once it is published', function () {
    privacyPage();

    $html = (string) $this->get('/register')->assertOk()->getContent();
    $consent = (string) str($html)->after('name="privacy"')->before('</label>');

    expect($consent)->toContain(__('account.register.privacy'))
        ->toContain('href="'.route('privacy').'"')
        ->toContain(__('account.register.privacy_link'));
});

it('seeds the privacy page once as a draft that only marks the missing text, and keeps an edited page', function () {
    $this->seed(ContentSeeder::class);

    $page = Page::query()->where('slug', Page::PRIVACY)->sole();

    expect($page->is_published)->toBeFalse()
        ->and(json_encode($page->blocks, JSON_UNESCAPED_UNICODE))->toContain('【ناقص: نص سياسة الخصوصية المعتمد من الجمعية】');

    $page->update(['blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>النص المعتمد</p>']]], 'is_published' => true]);
    $this->seed(ContentSeeder::class);

    expect(Page::query()->where('slug', Page::PRIVACY)->sole())
        ->is_published->toBeTrue()
        ->and(json_encode(Page::query()->where('slug', Page::PRIVACY)->sole()->blocks, JSON_UNESCAPED_UNICODE))->toContain('النص المعتمد');

    $this->get('/privacy')->assertOk();
});

// ملاحظة المشرف على #44: معرّفات الصفحات المنشورة بمفتاح مستقل عن ذاكرة المحتوى، فيمسحه أمر ما بعد النشر والاستعادة أيضاً
it('shows a page published directly in the database, as after a restore, once the content is refreshed', function () {
    $page = privacyPage(published: false);
    $link = 'href="'.route('privacy').'"';

    $this->get('/privacy')->assertNotFound();
    $this->get('/register')->assertDontSee($link, false);

    // بلا أحداث النموذج، كاستعادة القاعدة أو تعديلها المباشر
    Page::query()->whereKey($page->id)->update(['is_published' => true]);

    $this->artisan('ejaz:refresh-content')->assertSuccessful();

    $this->get('/privacy')->assertOk()->assertSee('نص السياسة للاختبار');
    $this->get('/register')->assertSee($link, false);
});

it('lists the published privacy page in the sitemap at its own address', function () {
    privacyPage();

    $this->get('/sitemap.xml')->assertOk()
        ->assertSee('<loc>'.url('/privacy').'</loc>', false)
        ->assertDontSee('/pages/privacy', false);
});
