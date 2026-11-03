<?php

use App\Actions\Accounts\BuildParticipationStats;
use App\Enums\RoleName;
use App\Models\CompetitionSubmission;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * بوابة المشتركين (requirements/12 §12 د): «عرض الاحصائيات للبرامج المسجل فيها، أو المنجزة» في لوحته،
 * من البيانات القائمة ولصاحب الحساب وحده.
 */
beforeEach(function () {
    $this->member = User::factory()->create();
    $this->member->assignRole(RoleName::Member->value);
});

it('counts the programs registered, the completed ones and the competition entries as listed in my entries', function () {
    ProgramRegistration::factory()->for($this->member)->create();
    ProgramRegistration::factory()->for($this->member)->create(['attended_at' => now()]);
    ProgramRegistration::factory()->for($this->member)->waitlisted()->create();
    ProgramRegistration::factory()->for($this->member)->cancelled()->create();
    ProgramRegistration::factory()->for($this->member)->for(Program::factory()->create(['deleted_at' => now()]))->create();
    ProgramRegistration::factory()->create(['attended_at' => now()]);

    CompetitionSubmission::factory()->for($this->member)->submitted()->create();
    CompetitionSubmission::factory()->for($this->member)->create();
    CompetitionSubmission::factory()->submitted()->create();

    expect(app(BuildParticipationStats::class)->handle($this->member))->toBe([
        'registered' => 2,
        'completed' => 1,
        // المرسلة والمسودة كلتاهما في «مشاركاتي»، فتُعدّان (البند ١٧)
        'entries' => 2,
    ]);
});

it('computes the statistics with a fixed number of queries', function () {
    ProgramRegistration::factory()->count(5)->for($this->member)->create();

    DB::enableQueryLog();
    app(BuildParticipationStats::class)->handle($this->member);

    expect(count(DB::getQueryLog()))->toBe(2);
});

it('shows the statistics card on the account dashboard', function () {
    ProgramRegistration::factory()->count(2)->for($this->member)->create();

    $this->actingAs($this->member)->get('/account')
        ->assertOk()
        ->assertSee(__('portals.member.stats.title'))
        ->assertSee(__('portals.member.stats.registered'))
        ->assertSee(__('portals.member.stats.completed'))
        ->assertSee(__('portals.member_entries'));
});

it('shows nobody else\'s numbers', function () {
    ProgramRegistration::factory()->count(4)->create(['attended_at' => now()]);

    expect(app(BuildParticipationStats::class)->handle($this->member))->toBe(['registered' => 0, 'completed' => 0, 'entries' => 0]);
});

it('keeps registration and following entries reachable for a member', function () {
    $this->actingAs($this->member);

    $this->get(route('account.programs.index'))->assertOk();
    $this->get(route('account.competitions'))->assertOk();
    $this->get('/admin')->assertForbidden();
});
