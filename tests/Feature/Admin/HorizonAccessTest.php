<?php

use App\Enums\RoleName;
use App\Models\User;

it('closes the queue dashboard to guests and ordinary users', function () {
    $this->get('/horizon')->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Member))->get('/horizon')->assertForbidden();
    $this->actingAs(userWithRole(RoleName::ContentEditor))->get('/horizon')->assertForbidden();
});

it('requires two-factor even for the super admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::TechnicalAdmin->value);

    // يُحوَّل إلى إعداد التحقق الثنائي كاللوحة (EnsureTwoFactorIsEnabled في وسطاء Horizon — الاختبار الأمني 0.14.0)،
    // ولا تُقرأ واجهته
    $this->actingAs($admin)->get('/horizon')->assertRedirect(route('account.security'));
    $this->get('/horizon/api/stats')->assertRedirect(route('account.security'));
});

it('opens the queue dashboard to the super admin with two-factor', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin))->get('/horizon')->assertOk();
});
