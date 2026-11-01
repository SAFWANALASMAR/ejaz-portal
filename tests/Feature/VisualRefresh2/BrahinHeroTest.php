<?php

use App\Models\Competition;
use App\Support\HomeShowcase;
use Database\Seeders\BrahinCompetitionSeeder;

/*
| بطل صفحة مسابقة الطلاب على التصور النهائي (Final-Competition — تدقيق المشرف): شارة الموسم وسطر «بالتنسيق مع» من نصوص
| الصفحة في اللوحة، وبطاقة زجاجية بمجموع جدول الجوائز يعدّ عند التحميل، وزر «شارك واربح» من المكوّن المشترك إلى وجهة زر
| العمود نفسها. ولا شيء منها لمسابقة عامة.
*/

beforeEach(function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);

    (new BrahinCompetitionSeeder)->run();
    $this->competition = Competition::query()->where('slug', 'brahin-1448')->sole();
    $this->competition->forceFill(['is_published' => true, 'opens_at' => now()->subDay(), 'closes_at' => now()->addWeek()])->save();
});

it('shows the season badge, the partner line and the prize card with the total from the prize table', function () {
    $total = HomeShowcase::prizeTotal($this->competition);
    expect($total)->toBeGreaterThan(0);

    $html = $this->get('/competitions/brahin-1448')->assertOk()
        ->assertSee('الموسم الأول 1448هـ / 2026م')
        ->assertSee(__('ui.prize.coordination', ['partner' => 'وزارة التعليم']))
        ->assertSeeInOrder(['data-prize-total', __('ui.prize.hero_title'), __('ui.prize.currency'), 'data-count-on-load', number_format($total), __('ui.prize.cta')], false)
        ->getContent();

    // زر البطل وزر العمود من المكوّن نفسه وإلى الوجهة نفسها
    preg_match_all('/data-student-cta>\s*<a href="([^"]+)"/', $html, $links);
    expect($links[1])->toHaveCount(2)
        ->and($links[1][0])->toBe($links[1][1])
        ->and($links[1][0])->toBe(route('competitions.student.register', 'brahin-1448'));
});

it('hides the season badge when it is empty and keeps the section badge', function () {
    $content = (array) $this->competition->page_content;
    unset($content['season']);
    $this->competition->forceFill(['page_content' => $content])->save();

    $this->get('/competitions/brahin-1448')->assertOk()->assertDontSee('الموسم الأول 1448هـ / 2026م')->assertSee('data-hero-badge', false);
});

it('shows the closed state in the hero card without a button', function () {
    $this->competition->forceFill(['opens_at' => now()->subWeek(), 'closes_at' => now()->subMinute()])->save();

    $html = $this->get('/competitions/brahin-1448')->assertOk()->getContent();

    expect(substr_count($html, __('competitions.student.registration_closed')))->toBe(2)
        ->and($html)->not->toContain('class="hero-cta"');
});

it('keeps a general competition hero without the prize card', function () {
    $competition = Competition::factory()->create(['is_published' => true, 'student_mode' => false]);

    $this->get('/competitions/'.$competition->slug)->assertOk()->assertDontSee('data-prize-total', false)->assertDontSee('class="hero-cta"', false);
});
