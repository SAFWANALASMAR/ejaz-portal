<?php

use App\Support\MissingText;

/*
| علامات 【ناقص】 في نصوص الرأس والتذييل والرئيسية (مراجعة #111): تظهر في التطوير والتجريبية، ولا يراها زائر الإنتاج كـ x-missing.
*/

it('shows the missing marks outside production', function () {
    expect(MissingText::shown('برقم 【ناقص: رقم الترخيص】'))->toBe('برقم 【ناقص: رقم الترخيص】')
        ->and(MissingText::shown('نص معتمد'))->toBe('نص معتمد')
        ->and(MissingText::shown('  '))->toBeNull();

    $this->get('/')->assertOk()->assertSee('【ناقص: رقم الترخيص】');
});

it('hides the missing marks from production visitors and keeps approved texts', function () {
    app()->instance('env', 'production');

    expect(MissingText::shown('برقم 【ناقص: رقم الترخيص】'))->toBeNull()
        ->and(MissingText::shown('نص معتمد'))->toBe('نص معتمد');

    $this->get('/contact')->assertOk()
        ->assertDontSee('【ناقص: رقم الترخيص】')
        ->assertDontSee('【ناقص: ساعات العمل】');
});
