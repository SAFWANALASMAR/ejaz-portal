<?php

use App\Actions\Users\AnonymizeUser;
use App\Actions\Users\ChangeUserRoles;
use App\Actions\Users\CreateStaffUser;
use App\Actions\Users\UpdateUserAccount;
use App\Enums\RoleName;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\SuperAdminRoleChanged;
use App\Support\LoginIdentifier;
use Database\Seeders\PermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->admin = userWithRole(RoleName::SuperAdmin));

it('keeps the users screen for those allowed to see personal data', function () {
    $this->actingAs($this->admin)->get(UserResource::getUrl('index'))->assertOk();
    $this->actingAs(userWithRole(RoleName::ContentEditor))->get(UserResource::getUrl('index'))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(UserResource::getUrl('index'))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Secretary))->get(UserResource::getUrl('index'))->assertForbidden();
});

it('forbids the user pages by record to those without user permissions', function () {
    $member = User::factory()->create();

    foreach ([RoleName::ContentEditor, RoleName::BoardMember] as $role) {
        $this->actingAs(userWithRole($role))->get(UserResource::getUrl('edit', ['record' => $member]))->assertForbidden();
        $this->actingAs(userWithRole($role))->get(UserResource::getUrl('create'))->assertForbidden();
    }
});

it('finds a user by any spelling of the phone number', function () {
    $member = User::factory()->create(['name' => 'صاحب الرقم', 'phone' => '+966512349876']);
    User::factory()->create(['name' => 'شخص آخر', 'phone' => '+966598765432']);

    $this->actingAs($this->admin);

    $spellings = [
        '0512349876', '051 234 9876', '٠٥١٢٣٤٩٨٧٦', '+966512349876', '512349876',
        '+966 51 234 9876', '00966 512349876', '966 512349876', '+966-51-234-9876', '+٩٦٦ ٥١ ٢٣٤ ٩٨٧٦',
    ];

    foreach ($spellings as $search) {
        Livewire::test(ListUsers::class)
            ->searchTable($search)
            ->assertCanSeeTableRecords([$member])
            ->assertCountTableRecords(1);
    }
});

it('still searches names word by word', function () {
    $member = User::factory()->create(['name' => 'محمد بن سعد العتيبي']);
    User::factory()->create(['name' => 'محمد الحربي']);

    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)
        ->searchTable('محمد العتيبي')
        ->assertCanSeeTableRecords([$member])
        ->assertCountTableRecords(1);
});

it('creates a staff account that sets its own password by email', function () {
    Notification::fake();
    $this->actingAs($this->admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'موظف الجمعية',
            'email' => 'Staff@Example.test',
            'phone' => '0512345678',
            'roles' => [RoleName::ContentEditor->value],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $staff = User::where('email', 'staff@example.test')->firstOrFail();

    expect($staff->phone)->toBe('+966512345678')
        ->and($staff->hasRole(RoleName::ContentEditor->value))->toBeTrue();

    Notification::assertSentTo($staff, ResetPasswordNotification::class);
});

it('rejects an email or phone already in use, whatever the spelling', function () {
    User::factory()->create(['email' => 'taken@example.test', 'phone' => '+966512345678']);
    $this->actingAs($this->admin);

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'شخص جديد', 'email' => 'TAKEN@example.test', 'phone' => '٠٥١٢٣٤٥٦٧٨'])
        ->call('create')
        ->assertHasFormErrors(['email', 'phone']);
});

it('lets only the super admin change roles, and logs the change', function () {
    $member = User::factory()->create();
    $member->assignRole(RoleName::Member->value);

    app(ChangeUserRoles::class)->handle($this->admin, $member, [RoleName::Secretary->value]);

    expect($member->fresh()->hasRole(RoleName::Secretary->value))->toBeTrue();

    $log = Activity::where('event', 'roles_changed')->firstOrFail();
    expect($log->properties['old'])->toBe([RoleName::Member->value])
        ->and($log->properties['new'])->toBe([RoleName::Secretary->value]);

    $editor = userWithRole(RoleName::ContentEditor);
    expect(fn () => app(ChangeUserRoles::class)->handle($editor, $member, [RoleName::SuperAdmin->value]))
        ->toThrow(AuthorizationException::class);
});

it('refuses to create a staff account without permission, wherever it is called from', function () {
    $editor = userWithRole(RoleName::ContentEditor);

    expect(fn () => app(CreateStaffUser::class)->handle($editor, ['name' => 'موظف', 'email' => 'x@example.test'], []))
        ->toThrow(AuthorizationException::class);

    expect(User::where('email', 'x@example.test')->exists())->toBeFalse();
});

it('drops the verification of a phone or email changed by an administrator', function () {
    Notification::fake();
    $member = User::factory()->create([
        'email' => 'member@example.test',
        'phone' => '+966512345678',
        'phone_verified_at' => now(),
    ]);

    app(UpdateUserAccount::class)->handle($this->admin, $member, [
        'name' => $member->name,
        'email' => 'member@example.test',
        'phone' => '+966598765432',
    ]);

    expect($member->fresh()->phone_verified_at)->toBeNull()
        ->and($member->fresh()->email_verified_at)->not->toBeNull();

    app(UpdateUserAccount::class)->handle($this->admin, $member->fresh(), [
        'name' => $member->name,
        'email' => 'new@example.test',
        'phone' => '+966598765432',
    ]);

    expect($member->fresh()->email_verified_at)->toBeNull();
});

it('keeps the verification when the phone and email do not change', function () {
    $member = User::factory()->create(['phone' => '+966512345678', 'phone_verified_at' => now()]);

    app(UpdateUserAccount::class)->handle($this->admin, $member, [
        'name' => 'اسم جديد',
        'email' => $member->email,
        'phone' => '+966512345678',
    ]);

    expect($member->fresh()->hasVerifiedPhone())->toBeTrue()
        ->and($member->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('keeps role assignment for the super admin even if the permission file is changed', function () {
    config(['permissions.roles.secretary' => ['requests.*', 'users.*']]);
    $this->seed(PermissionsSeeder::class);

    $secretary = userWithRole(RoleName::Secretary);
    $member = User::factory()->create();
    $member->assignRole(RoleName::Member->value);

    expect(fn () => app(ChangeUserRoles::class)->handle($secretary, $secretary, [RoleName::SuperAdmin->value]))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(ChangeUserRoles::class)->handle($secretary, $member, [RoleName::ContentEditor->value]))
        ->toThrow(AuthorizationException::class);

    expect($secretary->fresh()->hasRole(RoleName::SuperAdmin->value))->toBeFalse();
});

it('lets only the super admin edit accounts that hold an administrative role', function () {
    config(['permissions.roles.secretary' => ['requests.*', 'users.*']]);
    $this->seed(PermissionsSeeder::class);

    $secretary = userWithRole(RoleName::Secretary);
    $editor = userWithRole(RoleName::ContentEditor);
    $member = User::factory()->create();
    $member->assignRole(RoleName::Member->value);

    expect($secretary->can('update', $editor))->toBeFalse()
        ->and($secretary->can('update', $member))->toBeTrue()
        ->and($this->admin->can('update', $editor))->toBeTrue();
});

it('creates a member account without roles for someone who may create but not assign roles', function () {
    Notification::fake();
    config(['permissions.roles.secretary' => ['requests.*', 'users.*']]);
    $this->seed(PermissionsSeeder::class);

    $created = app(CreateStaffUser::class)->handle(userWithRole(RoleName::Secretary), ['name' => 'عضو جديد', 'email' => 'new@example.test'], []);

    expect($created->getRoleNames()->all())->toBe([RoleName::Member->value]);
});

it('does not let the super admin remove their own role', function () {
    expect(fn () => app(ChangeUserRoles::class)->handle($this->admin, $this->admin, [RoleName::Member->value]))
        ->toThrow(AuthorizationException::class);

    expect($this->admin->fresh()->hasRole(RoleName::SuperAdmin->value))->toBeTrue();
});

it('emails every super admin when the super admin role is granted or revoked', function () {
    Notification::fake();
    $other = userWithRole(RoleName::SuperAdmin);
    $member = User::factory()->create();

    app(ChangeUserRoles::class)->handle($this->admin, $member, [RoleName::SuperAdmin->value]);
    app(ChangeUserRoles::class)->handle($this->admin, $member, [RoleName::Member->value]);

    Notification::assertSentTo($other, SuperAdminRoleChanged::class, fn ($mail) => $mail->granted);
    Notification::assertSentTo($other, SuperAdminRoleChanged::class, fn ($mail) => ! $mail->granted);
    // من سُحب دوره يصله تنبيه السحب أيضاً، فلا يخفى عليه
    Notification::assertSentTo($member->fresh(), SuperAdminRoleChanged::class, fn ($mail) => ! $mail->granted);
});

it('tells a super admin when another removes their role, and names both in the mail', function () {
    Notification::fake();
    $victim = userWithRole(RoleName::SuperAdmin, ['email' => 'victim@example.test']);

    app(ChangeUserRoles::class)->handle($this->admin, $victim, [RoleName::Member->value]);

    Notification::assertSentTo($victim, SuperAdminRoleChanged::class, function (SuperAdminRoleChanged $alert) use ($victim) {
        $mail = $alert->toMail($victim);

        expect($mail->subject)->toBe(__('users.super_admin_mail.revoked_subject'))
            ->and((string) $mail->render())->toContain('victim@example.test')->toContain($this->admin->email);

        return true;
    });
    Notification::assertSentTo($this->admin, SuperAdminRoleChanged::class);
});

it('queues work only after the surrounding transaction commits', function () {
    expect(config('queue.connections.redis.after_commit'))->toBeTrue();
});

it('locks an anonymized account against edits and reactivation', function () {
    $gone = User::factory()->create(['deletion_requested_at' => now(), 'anonymized_at' => now(), 'is_active' => false]);

    expect($this->admin->can('update', $gone))->toBeFalse()
        ->and(fn () => app(UpdateUserAccount::class)->handle($this->admin, $gone, [
            'name' => 'محاولة',
            'email' => 'revive@example.test',
            'is_active' => true,
        ]))->toThrow(AuthorizationException::class);

    $this->actingAs($this->admin)->get(UserResource::getUrl('edit', ['record' => $gone]))->assertForbidden();
    expect($gone->fresh()->is_active)->toBeFalse();
});

it('gives no roles to an anonymized account', function () {
    $gone = User::factory()->create(['deletion_requested_at' => now(), 'anonymized_at' => now(), 'is_active' => false]);

    expect($this->admin->can('assignRoles', $gone))->toBeFalse()
        ->and(fn () => app(ChangeUserRoles::class)->handle($this->admin, $gone, [RoleName::ContentEditor->value]))
        ->toThrow(AuthorizationException::class);
});

it('explains in the form why the super admin cannot remove their own role', function () {
    $this->actingAs($this->admin);

    $component = Livewire::test(EditUser::class, ['record' => $this->admin->getRouteKey()])
        ->fillForm(['roles' => [RoleName::ContentEditor->value]])
        ->call('save');

    expect($component->errors()->first('data.roles'))->toBe(__('users.cannot_demote_self'))
        ->and($this->admin->fresh()->hasRole(RoleName::SuperAdmin->value))->toBeTrue();
});

it('does not let anyone deactivate their own account', function () {
    expect(fn () => app(UpdateUserAccount::class)->handle($this->admin, $this->admin, [
        'name' => $this->admin->name,
        'email' => $this->admin->email,
        'is_active' => false,
    ]))->toThrow(AuthorizationException::class);

    expect($this->admin->fresh()->is_active)->toBeTrue();
});

it('deactivates another account from the edit screen', function () {
    $member = User::factory()->create();
    $this->actingAs($this->admin);

    Livewire::test(EditUser::class, ['record' => $member->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($member->fresh()->is_active)->toBeFalse();
});

it('anonymizes an account whose owner asked for deletion, and only then', function () {
    $requester = User::factory()->create([
        'name' => 'صاحب الطلب',
        'email' => 'requester@example.test',
        'phone' => '+966512345678',
        'deletion_requested_at' => now(),
    ]);
    $requester->assignRole(RoleName::Member->value);
    $other = User::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('anonymize')->table($other))
        ->callAction(TestAction::make('anonymize')->table($requester));

    $requester->refresh();

    expect($requester->name)->toBe(__('users.anonymized_name'))
        ->and($requester->email)->toBe('deleted-'.$requester->id.'@deleted.invalid')
        ->and($requester->phone)->toBeNull()
        ->and($requester->is_active)->toBeFalse()
        ->and($requester->anonymized_at)->not->toBeNull()
        ->and($requester->getRoleNames())->toBeEmpty()
        ->and(Activity::where('event', 'anonymized')->exists())->toBeTrue();
});

it('leaves no trace of the person in the activity log, reset links or notifications', function () {
    $requester = User::factory()->withTwoFactor()->create([
        'name' => 'اسم سري للاختبار',
        'email' => 'secret.person@example.test',
        'phone' => '+966598765432',
    ]);

    // آثار سابقة: تعديل بيانات، ودخول فاشل ببصمة المعرّف، ودخول ناجح بعنوان IP، ورابط استعادة
    $requester->update(['name' => 'اسم سري معدّل']);
    $this->post('/login', ['login' => '0598765432', 'password' => 'wrong-password']);
    $this->post('/login', ['login' => 'secret.person@example.test', 'password' => 'password']);
    $this->post('/logout');
    Password::broker()->createToken($requester);
    $requester->forceFill(['deletion_requested_at' => now()])->save();

    app(AnonymizeUser::class)->handle($this->admin, $requester);

    $requester->refresh();
    expect($requester->two_factor_secret)->toBeNull()
        ->and($requester->two_factor_confirmed_at)->toBeNull();

    $logs = Activity::all()->map(fn (Activity $log) => json_encode([
        $log->description, $log->attribute_changes, $log->properties,
    ], JSON_UNESCAPED_UNICODE))->implode("\n");

    expect($logs)
        ->not->toContain('secret.person@example.test')
        ->not->toContain('598765432')
        ->not->toContain('اسم سري')
        ->not->toContain(LoginIdentifier::hash('0598765432'))
        ->not->toContain('"ip"');

    expect(DB::table('password_reset_tokens')->where('email', 'secret.person@example.test')->exists())->toBeFalse()
        ->and(Activity::where('event', 'anonymized')->exists())->toBeTrue()
        ->and(Activity::where('event', 'personal_data_changed')->exists())->toBeTrue();
});

it('scrubs every log entry of the person, however many there are', function () {
    $requester = User::factory()->create(['email' => 'many.logs@example.test', 'deletion_requested_at' => now()]);
    $hash = LoginIdentifier::hash('many.logs@example.test');
    $rows = fn (int $count, array $row): array => array_fill(0, $count, $row + [
        'log_name' => 'security', 'created_at' => now(), 'updated_at' => now(),
    ]);

    // أكثر من صفحة واحدة (1000) من المحاولات بالبصمة، ومن الدخول بالحساب
    DB::table('activity_log')->insert($rows(1200, [
        'event' => 'login_lockout', 'description' => 'login_lockout',
        'properties' => json_encode(['ip' => '203.0.113.5', 'identifier_hash' => $hash]),
    ]));
    DB::table('activity_log')->insert($rows(300, [
        'event' => 'login', 'description' => 'login',
        'subject_type' => $requester->getMorphClass(), 'subject_id' => $requester->id,
        'properties' => json_encode(['ip' => '203.0.113.5']),
    ]));

    app(AnonymizeUser::class)->handle($this->admin, $requester);

    expect(Activity::where('properties->identifier_hash', $hash)->count())->toBe(0)
        ->and(Activity::where('properties->ip', '203.0.113.5')->count())->toBe(0);
});

it('records which personal fields changed without their values', function () {
    $member = User::factory()->create(['name' => 'الاسم الأول', 'email' => 'first@example.test']);

    $member->update(['name' => 'الاسم الثاني', 'email' => 'second@example.test']);

    $log = Activity::where('event', 'personal_data_changed')->firstOrFail();
    expect($log->properties['fields'])->toBe(['name', 'email']);

    $all = Activity::all()->map(fn (Activity $log) => json_encode([$log->attribute_changes, $log->properties], JSON_UNESCAPED_UNICODE))->implode("\n");
    expect($all)->not->toContain('first@example.test')->not->toContain('الاسم الثاني');
});

it('refuses to anonymize without a request, twice, or one\'s own account', function () {
    $noRequest = User::factory()->create();
    $done = User::factory()->create(['deletion_requested_at' => now(), 'anonymized_at' => now()]);
    $this->admin->forceFill(['deletion_requested_at' => now()])->save();

    foreach ([$noRequest, $done, $this->admin] as $target) {
        expect(fn () => app(AnonymizeUser::class)->handle($this->admin, $target))->toThrow(AuthorizationException::class);
    }
});
