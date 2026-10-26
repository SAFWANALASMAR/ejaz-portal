<?php

use App\Enums\MeetingBody;
use App\Enums\RoleName;
use App\Filament\Resources\FormEntries\Pages\CreateFormEntry;
use App\Models\Committee;
use App\Models\FormTemplate;
use App\Models\Meeting;
use App\Models\Payment;
use App\Models\User;
use Livewire\Livewire;

/*
| IDOR (الاختبار الأمني 0.14.0): ما لم تغطه AccessMatrixTest وRoleMatrixTest من الوصول برقم سجل غيره.
*/

// دالة اسم الخيار تُستدعى من المتصفح برقم أي حساب، فكانت تسرد أسماء الحسابات كلها بالأرقام المتتالية لمن يعبّئ النماذج
it('names only a person from the form audience in the owner field, whatever id the browser sends', function () {
    $template = FormTemplate::factory()->create(['audience' => 'staff']);
    $colleague = userWithRole(RoleName::Accountant, ['name' => 'زميل من الموظفين']);
    $outsider = User::factory()->create(['name' => 'مستفيد خارج الجمهور']);

    $this->actingAs(userWithRole(RoleName::Secretary));

    Livewire::test(CreateFormEntry::class)
        ->fillForm(['form_template_id' => $template->id, 'person_id' => $outsider->id])
        ->assertDontSee($outsider->name);

    Livewire::test(CreateFormEntry::class)
        ->fillForm(['form_template_id' => $template->id, 'person_id' => $colleague->id])
        ->assertSee($colleague->name);
});

// من يعدّل الاجتماعات بلا view_all لا يلغي اجتماع لجنة لا يراها ولا يعلن انعقاده (كالتعديل والحذف)
it('keeps cancelling and marking held to meetings the account can see', function () {
    $editor = User::factory()->withTwoFactor()->create();
    $editor->givePermissionTo(['meetings.view', 'meetings.update']);
    $meeting = Meeting::factory()->create(['body' => MeetingBody::Committee, 'committee_id' => Committee::factory(), 'starts_at' => now()->subHour()]);

    expect($editor->can('cancel', $meeting))->toBeFalse()
        ->and($editor->can('markHeld', $meeting))->toBeFalse();

    $editor->givePermissionTo('meetings.view_all');
    $editor = $editor->fresh();

    expect($editor->can('cancel', $meeting))->toBeTrue()
        ->and($editor->can('markHeld', $meeting))->toBeTrue();
});

// صفحة رجوع الدفع لعملية حساب لصاحبها وحده، ولو عُرف معرّفها (الوحدة مؤرشفة خلف PAYMENTS_ENABLED)
it('answers 404 on the payment return page of another account', function () {
    config(['payments.enabled' => true]);
    $payment = Payment::factory()->create(['user_id' => User::factory()]);

    $this->actingAs(User::factory()->create())->get(route('payments.return', $payment))->assertNotFound();
    auth()->logout();
    $this->get(route('payments.return', $payment))->assertNotFound();
});
