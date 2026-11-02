<?php

use App\Enums\MembershipStatus;
use App\Enums\RoleName;
use App\Models\AssemblyMembership;
use App\Support\AssemblyMembersCount;

/*
| عدد أعضاء الجمعية العمومية الفعّالين (المدير عبر صفوان والمشرف، 1 نوفمبر 2026):
| - في بوابة المجلس لمديري سجل الأعضاء في اللوحة وحدهم (assembly_members.update)، لا لكل عضو.
| - وفي صفحة الحوكمة للجميع: رقم محسوب من البيانات ومخزّن، بلا أسماء ولا بيانات شخصية، ولا يظهر وهو صفر.
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

it('shows the active members count on the governance page to every visitor, without names', function () {
    $members = AssemblyMembership::factory()->count(3)->create();
    AssemblyMembership::factory()->create(['status' => MembershipStatus::Suspended]);

    $html = $this->get('/governance')->assertOk()->getContent();
    $block = (string) str($html)->after('data-assembly-members-count')->before('</section>');

    expect($block)->toContain(__('governance.members_count.label'))
        ->toMatch('/>\s*3\s*</u')
        ->not->toContain(e($members->first()->user->name));
});

it('hides the governance count when there are no active members', function () {
    $this->get('/governance')->assertOk()->assertDontSee('data-assembly-members-count', false);
});

it('caches the count and refreshes it when a membership or an account changes', function () {
    $membership = AssemblyMembership::factory()->create();

    expect(AssemblyMembersCount::get())->toBe(1);

    AssemblyMembership::factory()->create();
    expect(AssemblyMembersCount::get())->toBe(2);

    $membership->update(['status' => MembershipStatus::Suspended]);
    expect(AssemblyMembersCount::get())->toBe(1);

    AssemblyMembership::query()->active()->first()->user->forceFill(['is_active' => false])->save();
    expect(AssemblyMembersCount::get())->toBe(0);

    $this->get('/governance')->assertOk()->assertDontSee('data-assembly-members-count', false);
});

it('serves the cached count without querying the memberships again', function () {
    AssemblyMembership::factory()->create();
    AssemblyMembersCount::get();

    DB::enableQueryLog();
    AssemblyMembersCount::get();

    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'assembly_memberships')))->toBeEmpty();
});

it('refreshes the cached governance page when a member joins', function () {
    $this->get('/governance')->assertOk()->assertDontSee('data-assembly-members-count', false);

    AssemblyMembership::factory()->create();

    $this->get('/governance')->assertOk()->assertSee('data-assembly-members-count', false);
});
