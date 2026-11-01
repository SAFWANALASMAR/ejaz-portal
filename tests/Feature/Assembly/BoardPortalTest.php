<?php

use App\Enums\AgendaItemStatus;
use App\Enums\MeetingBody;
use App\Enums\RoleName;
use App\Models\AssemblyMembership;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Models\User;

/*
| بوابة مجلس الإدارة والجمعية العمومية في /assembly (Final-Board — المرحلة د من #111): عرض لما هو قائم، فلا يرى العضو إلا ما يحق
| له اليوم: اجتماعات الجمعية العمومية ومحاضرها المعتمدة، والمجلس وقراراته لمن يراها في اللوحة (meetings.view).
*/

beforeEach(function () {
    $this->member = AssemblyMembership::factory()->create()->user;
});

it('greets the member with the board stats, shortcuts and upcoming meetings', function () {
    Meeting::factory()->create(['title' => 'اجتماع الجمعية القادم']);

    $this->actingAs($this->member)->get('/assembly')->assertOk()
        ->assertSee(__('assembly.portal.welcome', ['name' => $this->member->name]))
        ->assertSee(__('assembly.portal.members'))
        ->assertSee(__('assembly.portal.meetings_this_year'))
        ->assertSee('اجتماع الجمعية القادم')
        ->assertSee(route('assembly.minutes.index'))
        ->assertSee(__('assembly.portal.records'));
});

it('shows the Teams button only for a meeting that has a join link', function () {
    $linked = Meeting::factory()->online()->create(['title' => 'اجتماع برابط', 'starts_at' => now()->addDay()]);
    $linked->forceFill(['online_join_url' => 'https://teams.microsoft.com/l/meetup-join/board-secret'])->save();
    Meeting::factory()->online()->create(['title' => 'اجتماع بلا رابط', 'starts_at' => now()->addDays(3)]);

    $this->actingAs($this->member)->get('/assembly')->assertOk()
        ->assertSee('اجتماع بلا رابط')
        ->assertSee(__('assembly.portal.join_next'))
        ->assertSee('meetup-join/board-secret');

    $linked->forceFill(['online_join_url' => null])->save();

    $this->get('/assembly')->assertOk()
        ->assertDontSee(__('assembly.portal.join_next'))
        ->assertDontSee('data-board-join', false);
});

it('keeps board meetings and decisions away from members who cannot see them in the panel', function () {
    $board = Meeting::factory()->create(['title' => 'اجتماع المجلس السري', 'body' => MeetingBody::Board]);
    MeetingAgendaItem::factory()->for($board)->create(['decision' => 'قرار المجلس التجريبي', 'status' => AgendaItemStatus::InProgress]);

    $this->actingAs($this->member)->get('/assembly')->assertOk()
        ->assertDontSee('اجتماع المجلس السري')
        ->assertDontSee('قرار المجلس التجريبي')
        ->assertDontSee(__('assembly.portal.open_assignments'));

    $boardMember = userWithRole(RoleName::BoardMember);

    $this->actingAs($boardMember)->get('/assembly')->assertOk()
        ->assertSee('اجتماع المجلس السري')
        ->assertSee('قرار المجلس التجريبي')
        ->assertSee(__('assembly.portal.open_assignments'));
});

it('keeps the visitor and non-member views as they were', function () {
    $this->get('/assembly')->assertOk()->assertDontSee(__('assembly.portal.stats'))->assertSee(__('assembly.index.login'));

    $this->actingAs(User::factory()->create())->get('/assembly')->assertOk()
        ->assertDontSee(__('assembly.portal.stats'))->assertSee(__('assembly.index.not_member'));
});
