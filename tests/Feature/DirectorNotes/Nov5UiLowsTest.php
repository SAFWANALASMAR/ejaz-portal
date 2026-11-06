<?php

use App\Support\HomeContent;

// منخفضات مراجعة المشرف على طلبي الدمج 198 و189 (6 نوفمبر 2026)

it('drops the research strip heading from the home content screen with its strip', function () {
    expect(HomeContent::TEXTS['headings'])->not->toContain('research')
        ->and(__('settings.home_content.headings_fields'))->not->toHaveKey('research')
        ->and(__('home.sections_final.headings'))->not->toHaveKey('research');
});

it('hides the auth intro on short desktop windows for the wide register card only', function () {
    $this->get('/register')->assertOk()->assertSee('max-height:45rem', false);
    $this->get('/login')->assertOk()->assertDontSee('max-height:45rem', false);
    $this->get('/forgot-password')->assertOk()->assertDontSee('max-height:45rem', false);
});

it('hides the empty challenge slot without a site key and carries the notice for the stand-in test', function () {
    $this->get('/register')->assertOk()
        ->assertSee('[&:not(:has(*))]:hidden', false)
        ->assertSee('data-notice="'.e(__('account.bot_challenge.notice')).'"', false);
});
