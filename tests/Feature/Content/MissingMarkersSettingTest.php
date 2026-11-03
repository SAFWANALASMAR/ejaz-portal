<?php

use App\Enums\RoleName;
use App\Models\User;
use App\Support\ContentCache;
use App\Support\MissingText;

/*
| ملاحظة صفوان ٣٠ (3 نوفمبر 2026): علامات 【ناقص】 لا يراها زائر رابط العرض. HIDE_MISSING_MARKERS=true يخفيها عن الزوار خارج الإنتاج،
| وتبقى لمن يدخل لوحة الإدارة، وتظهر للجميع في التطوير بلا الإعداد، ولا تظهر لأحد في الإنتاج.
*/

it('shows the markers to everyone in development without the setting', function () {
    config(['app.hide_missing_markers' => false]);

    expect(MissingText::visible())->toBeTrue()
        ->and(MissingText::shown('【ناقص: شعار فرعي معتمد】'))->toBe('【ناقص: شعار فرعي معتمد】');

    $this->get('/')->assertOk()->assertSee('【ناقص', false);
    $this->get('/contact')->assertOk()->assertSee('【ناقص', false);
});

it('hides the markers from visitors and members when the setting is on, with nothing in their place', function () {
    config(['app.hide_missing_markers' => true]);

    expect(MissingText::visible())->toBeFalse()
        ->and(MissingText::shown('【ناقص: شعار فرعي معتمد】'))->toBeNull()
        ->and(MissingText::shown('نص معتمد'))->toBe('نص معتمد');

    foreach (['/', '/contact', '/programs'] as $path) {
        $this->get($path)->assertOk()->assertDontSee('【', false)->assertDontSee(__('site.missing').':', false);
    }

    // صاحب حساب بلا دور إداري زائرٌ في هذا
    $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertDontSee('【', false);
});

it('keeps the markers for admins when the setting is on, without leaking their cached page to visitors', function () {
    config(['app.hide_missing_markers' => true]);

    // الزائر أولاً ثم المحرر ثم الزائر: كل منهما نسخته من ذاكرة المحتوى
    $this->get('/')->assertOk()->assertDontSee('【', false);

    $this->actingAs(userWithRole(RoleName::ContentEditor))->get('/')->assertOk()->assertSee('【ناقص', false);
    expect(MissingText::visible())->toBeTrue();

    auth()->logout();
    $this->get('/')->assertOk()->assertDontSee('【', false);
});

it('forgets both the visitor and the admin copies of a cached key', function () {
    config(['app.hide_missing_markers' => true]);
    $editor = userWithRole(RoleName::ContentEditor);

    ContentCache::remember('note30', fn (): string => 'visitor-old');
    $this->actingAs($editor);
    ContentCache::remember('note30', fn (): string => 'admin-old');

    ContentCache::forget('note30');

    expect(ContentCache::remember('note30', fn (): string => 'admin-new'))->toBe('admin-new');
    auth()->logout();
    expect(ContentCache::remember('note30', fn (): string => 'visitor-new'))->toBe('visitor-new');
});

it('hides the markers from everyone in production, admins included', function () {
    app()->instance('env', 'production');
    config(['app.hide_missing_markers' => false]);

    $this->actingAs(userWithRole(RoleName::ContentEditor));

    expect(MissingText::visible())->toBeFalse()
        ->and(MissingText::shown('【ناقص: ساعات العمل】'))->toBeNull();
});
