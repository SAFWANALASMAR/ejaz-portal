<?php

use App\Actions\Assistant\TestAssistantConnection;
use App\Enums\RoleName;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/*
| ما يُستدعى من المتصفح بـ $wire.call (الاختبار الأمني 0.14.0): الواجهة العامة وحساب المستخدم بلا مكون Livewire، فكل ما
| يُستدعى داخل لوحة الإدارة، وصلاحياته في RoleMatrixTest وAccessMatrixTest. وأي مكون عام يُضاف بعدها يُسقط هذا الاختبار
| حتى تُراجع دواله العامة وخصائصه (#[Locked]) ويُكتب له اختبار رفض.
*/

it('keeps every Livewire component inside the admin panel, none on the public site or the account', function () {
    $public = collect(File::allFiles(app_path()))
        ->reject(fn ($file) => str_starts_with($file->getPathname(), app_path('Filament')))
        ->map(fn ($file) => 'App\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname()))
        ->filter(fn (string $class) => class_exists($class) && is_subclass_of($class, Component::class))
        ->values()
        ->all();

    $tags = collect(File::allFiles(resource_path('views')))
        ->reject(fn ($file) => str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'filament'.DIRECTORY_SEPARATOR))
        ->filter(fn ($file) => preg_match('/@livewire\b|<livewire:/', $file->getContents()) === 1)
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($public)->toBe([])->and($tags)->toBe([]);
});

// كل اختبار اتصال طلب مدفوع إلى المزود، فخمسة في الدقيقة لكل حساب كاختبار تيمز (كان بلا حد)
it('rate-limits the assistant connection test per account', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $throttled = fn (): bool => rescue(
        fn () => app(TestAssistantConnection::class)->handle($admin, []) && false,
        fn ($e) => $e instanceof ValidationException && ($e->errors()['provider'][0] ?? null) === __('assistant.admin.settings.test_throttled'),
        report: false,
    );

    foreach (range(1, 5) as $ignored) {
        expect($throttled())->toBeFalse();
    }

    expect($throttled())->toBeTrue()
        ->and(rescue(fn () => app(TestAssistantConnection::class)->handle(userWithRole(RoleName::ExecutiveDirector), []) && false,
            fn ($e) => ($e->errors()['provider'][0] ?? null) === __('assistant.admin.settings.test_throttled'), report: false))->toBeFalse();
});
