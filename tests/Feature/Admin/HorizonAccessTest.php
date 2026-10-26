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

    $this->actingAs($admin)->get('/horizon')->assertForbidden();
});

it('opens the queue dashboard to the super admin with two-factor', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin))->get('/horizon')->assertOk();
});
