<?php

use App\Enums\RoleName;
use App\Models\AssemblyMembership;

/*
| عدد أعضاء الجمعية العمومية الفعّالين (المدير عبر صفوان والمشرف، 1 نوفمبر 2026):
| - في بوابة المجلس لمديري سجل الأعضاء في اللوحة وحدهم (assembly_members.update)، لا لكل عضو.
| - ولا يظهر في صفحة الحوكمة العامة (قرار صفوان، 4 نوفمبر 2026).
*/

function boardStats(string $html): string
{
    return (string) str($html)->after('data-board-stats')->before('</ul>');
}

it('shows the members count in the board portal to the members registry admins only', function () {
    $member = AssemblyMembership::factory()->create()->user;

    expect(boardStats($this->actingAs($member)->get('/assembly')->assertOk()->getContent()))
        ->not->toContain(__('assembly.portal.members'))
        ->toContain(__('assembly.portal.meetings_this_year'));

    $admin = userWithRole(RoleName::ExecutiveDirector);

    expect(boardStats($this->actingAs($admin)->get('/assembly')->assertOk()->getContent()))
        ->toContain(__('assembly.portal.members'));
});

it('does not show the members count on the public governance page', function () {
    AssemblyMembership::factory()->count(3)->create();

    $this->get('/governance')->assertOk()
        ->assertDontSee('data-assembly-members-count', false)
        ->assertDontSee('أعضاء الجمعية العمومية الفعّالون');
});
