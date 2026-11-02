<?php

use App\Actions\Achievements\SaveAchievement;
use App\Enums\AchievementStatus;
use App\Enums\RoleName;
use App\Filament\Resources\Achievements\AchievementResource;
use App\Filament\Resources\Achievements\Pages\CreateAchievement;
use App\Filament\Resources\Achievements\Pages\EditAchievement;
use App\Filament\Resources\Achievements\Pages\ListAchievements;
use App\Models\Achievement;
use App\Models\AssemblyMembership;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| «إنجازاتنا» (docs/decisions/2026-11-02-achievements.md): تضيفها الإدارة التنفيذية وحدها (السكرتير والمدير التنفيذي والمسؤول
| التقني)، والمجلس والجمعية العمومية يطّلعون على المنشور في بوابتهم، والزائر في الصفحة العامة. والمرفق في القرص الخاص برابط
| مؤقت موقّع يُفحص عنده النشر أو الصلاحية.
*/

beforeEach(function () {
    Storage::fake('local');
});

/**
 * إنجاز بمرفق محفوظ فعلاً في القرص الخاص.
 *
 * @param  array<string, mixed>  $attributes
 */
function storedAchievement(array $attributes = [], string $extension = 'pdf'): Achievement
{
    $achievement = Achievement::factory()->withAttachment($extension)->create($attributes);
    Storage::disk('local')->put((string) $achievement->attachment, $extension === 'pdf' ? pdfContents() : imageBytes($extension));

    return $achievement;
}

dataset('executive administration', [
    'secretary' => [RoleName::Secretary],
    'executive director' => [RoleName::ExecutiveDirector],
    'technical officer' => [RoleName::TechnicalAdmin],
]);

it('lets the executive administration add, publish, edit and delete an achievement from the panel, logged', function (RoleName $role) {
    $actor = userWithRole($role);
    $this->actingAs($actor);

    Livewire::test(CreateAchievement::class)
        ->fillForm([
            'title' => 'إنجاز الاختبار',
            'achieved_on' => '2026-10-15',
            'details' => "سطر أول\nسطر ثانٍ",
            'status' => AchievementStatus::Draft->value,
            'attachment' => UploadedFile::fake()->createWithContent('صورة الإنجاز.png', imageBytes('png')),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $achievement = Achievement::sole();
    expect($achievement->status)->toBe(AchievementStatus::Draft)
        ->and($achievement->achieved_on->toDateString())->toBe('2026-10-15')
        ->and($achievement->created_by)->toBe($actor->id)
        ->and($achievement->attachment)->toStartWith(Achievement::DIRECTORY.'/')
        ->and($achievement->attachment)->not->toContain('صورة')
        ->and($achievement->attachment_name)->toBe('صورة الإنجاز.png')
        ->and(Storage::disk('local')->exists((string) $achievement->attachment))->toBeTrue();

    Livewire::test(EditAchievement::class, ['record' => $achievement->uuid])
        ->fillForm(['title' => 'إنجاز منشور', 'status' => AchievementStatus::Published->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($achievement->fresh()->isPublished())->toBeTrue()->and($achievement->fresh()->title)->toBe('إنجاز منشور');

    Livewire::test(ListAchievements::class)
        ->assertCanSeeTableRecords([$achievement])
        ->callAction(TestAction::make(DeleteAction::class)->table($achievement));

    expect($achievement->fresh()->trashed())->toBeTrue();

    $events = Activity::query()->where('log_name', 'content')->whereMorphedTo('subject', $achievement)->orderBy('id')->get();
    expect($events->pluck('event')->all())->toBe(['created', 'updated', 'deleted'])
        ->and($events->pluck('causer_id')->unique()->all())->toBe([$actor->id]);
})->with('executive administration');

it('rejects the board, the general assembly, the content editor and guests from managing achievements', function () {
    $achievement = Achievement::factory()->create();

    foreach ([RoleName::BoardMember, RoleName::ContentEditor, RoleName::SuperAdmin] as $role) {
        $user = userWithRole($role);
        $this->actingAs($user);

        if ($role !== RoleName::SuperAdmin) {
            $this->get(AchievementResource::getUrl('index'))->assertForbidden();
        }

        $this->get(AchievementResource::getUrl('create'))->assertForbidden();
        $this->get(AchievementResource::getUrl('edit', ['record' => $achievement]))->assertForbidden();
        expect($user->can('delete', $achievement))->toBeFalse()
            ->and(fn () => app(SaveAchievement::class)->handle($user, new Achievement, ['title' => 'x', 'achieved_on' => '2026-01-01', 'details' => 'x', 'status' => 'published']))
            ->toThrow(AuthorizationException::class);
    }

    $member = AssemblyMembership::factory()->create()->user;
    $this->actingAs($member)->get(AchievementResource::getUrl('index'))->assertForbidden();
    expect(fn () => app(SaveAchievement::class)->handle($member, $achievement, ['title' => 'x', 'achieved_on' => '2026-01-01', 'details' => 'x', 'status' => 'published']))
        ->toThrow(AuthorizationException::class);

    auth()->logout();
    $this->get(AchievementResource::getUrl('create'))->assertRedirect();
    expect(Achievement::count())->toBe(1)->and($achievement->fresh()->title)->not->toBe('x');
});

it('rejects invalid input', function (array $data, string $field) {
    $this->actingAs(userWithRole(RoleName::Secretary));

    Livewire::test(CreateAchievement::class)
        ->fillForm([...['title' => 'عنوان', 'achieved_on' => '2026-10-01', 'details' => 'تفاصيل', 'status' => 'draft'], ...$data])
        ->call('create')
        ->assertHasFormErrors([$field]);

    expect(Achievement::count())->toBe(0);
})->with([
    'no title' => [['title' => ''], 'title'],
    'long title' => [['title' => str_repeat('ع', 201)], 'title'],
    'no date' => [['achieved_on' => null], 'achieved_on'],
    'no details' => [['details' => ''], 'details'],
    'unknown status' => [['status' => 'archived'], 'status'],
]);

it('rejects an attachment of the wrong type or too large, and deletes the rejected file', function () {
    $actor = userWithRole(RoleName::ExecutiveDirector);
    $this->actingAs($actor);

    // نوع غير مقبول في حقل الرفع
    Livewire::test(CreateAchievement::class)
        ->fillForm(['title' => 'عنوان', 'achieved_on' => '2026-10-01', 'details' => 'تفاصيل', 'status' => 'draft',
            'attachment' => UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>')])
        ->call('create')
        ->assertHasFormErrors(['attachment']);

    // أكبر من الحد في حقل الرفع
    Livewire::test(CreateAchievement::class)
        ->fillForm(['title' => 'عنوان', 'achieved_on' => '2026-10-01', 'details' => 'تفاصيل', 'status' => 'draft',
            'attachment' => UploadedFile::fake()->create('big.pdf', 20481, 'application/pdf')])
        ->call('create')
        ->assertHasFormErrors(['attachment']);

    expect(Achievement::count())->toBe(0);

    // والإجراء نفسه يفحص المحتوى لا الاسم: نص باسم PDF يُرفض ويُحذف، وملف خارج المجلد لا يُقبل
    $fake = storedPrivateFile(Achievement::DIRECTORY, 'not a pdf at all', 'pdf');
    expect(fn () => app(SaveAchievement::class)->handle($actor, new Achievement, ['title' => 'عنوان', 'achieved_on' => '2026-10-01', 'details' => 'تفاصيل', 'status' => 'draft', 'attachment' => $fake, 'attachment_name' => 'x.pdf']))
        ->toThrow(ValidationException::class);
    expect(Storage::disk('local')->exists($fake))->toBeFalse();

    $outside = storedPrivateFile('job-files');
    expect(fn () => app(SaveAchievement::class)->handle($actor, new Achievement, ['title' => 'عنوان', 'achieved_on' => '2026-10-01', 'details' => 'تفاصيل', 'status' => 'draft', 'attachment' => $outside, 'attachment_name' => 'x.pdf']))
        ->toThrow(ValidationException::class);
    expect(Storage::disk('local')->exists($outside))->toBeTrue()->and(Achievement::count())->toBe(0);

    // وأكبر من 20 ميجابايت في القرص يُرفض قبل قراءته
    $huge = Achievement::DIRECTORY.'/'.fake()->uuid().'.pdf';
    Storage::disk('local')->put($huge, pdfContents(str_repeat('a', 20 * 1024 * 1024)));
    expect(fn () => app(SaveAchievement::class)->handle($actor, new Achievement, ['title' => 'عنوان', 'achieved_on' => '2026-10-01', 'details' => 'تفاصيل', 'status' => 'draft', 'attachment' => $huge, 'attachment_name' => 'x.pdf']))
        ->toThrow(ValidationException::class);
});

it('takes the download name extension from the checked file type, and does not reuse another achievement file', function () {
    $actor = userWithRole(RoleName::Secretary);
    $png = storedPrivateFile(Achievement::DIRECTORY, imageBytes('png'), 'png');

    $achievement = app(SaveAchievement::class)->handle($actor, new Achievement, ['title' => 'عنوان', 'achieved_on' => '2026-10-01', 'details' => 'تفاصيل', 'status' => 'published', 'attachment' => $png, 'attachment_name' => 'evil.html']);
    expect($achievement->attachment_name)->toBe('evil.png')->and($achievement->attachmentIsImage())->toBeTrue();

    expect(fn () => app(SaveAchievement::class)->handle($actor, new Achievement, ['title' => 'آخر', 'achieved_on' => '2026-10-01', 'details' => 'تفاصيل', 'status' => 'draft', 'attachment' => $png, 'attachment_name' => 'x.png']))
        ->toThrow(ValidationException::class);
    expect(Storage::disk('local')->exists($png))->toBeTrue();

    // استبدال المرفق يحذف السابق
    $pdf = storedPrivateFile(Achievement::DIRECTORY);
    app(SaveAchievement::class)->handle($actor, $achievement, ['title' => 'عنوان', 'achieved_on' => '2026-10-01', 'details' => 'تفاصيل', 'status' => 'published', 'attachment' => $pdf, 'attachment_name' => 'تقرير.pdf']);
    expect(Storage::disk('local')->exists($png))->toBeFalse()->and($achievement->fresh()->attachment_name)->toBe('تقرير.pdf');

    // وإزالة المرفق تمسح أعمدته وتحذف ملفه
    app(SaveAchievement::class)->handle($actor, $achievement, ['title' => 'عنوان', 'achieved_on' => '2026-10-01', 'details' => 'تفاصيل', 'status' => 'published', 'attachment' => null]);
    $achievement->refresh();
    expect($achievement->attachment)->toBeNull()->and($achievement->attachment_name)->toBeNull()
        ->and($achievement->attachmentUrl())->toBeNull()
        ->and(Storage::disk('local')->exists($pdf))->toBeFalse();
});

it('keeps a valid upload when a text field is rejected, so the corrected form saves with it', function () {
    $this->actingAs(userWithRole(RoleName::Secretary));

    $form = Livewire::test(CreateAchievement::class)
        ->fillForm(['title' => "عنوان \u{202E}مقلوب", 'achieved_on' => '2026-10-01', 'details' => 'تفاصيل', 'status' => 'draft',
            'attachment' => UploadedFile::fake()->createWithContent('تقرير.pdf', pdfContents())])
        ->call('create')
        ->assertHasFormErrors(['title']);

    $form->fillForm(['title' => 'عنوان مصحح'])->call('create')->assertHasNoFormErrors();

    $achievement = Achievement::sole();
    expect($achievement->attachment_name)->toBe('تقرير.pdf')->and(Storage::disk('local')->exists((string) $achievement->attachment))->toBeTrue();
});

it('rejects a future date and an edit of a deleted achievement until it is restored', function () {
    $actor = userWithRole(RoleName::ExecutiveDirector);
    $this->travelTo(Carbon::parse('2026-11-02 12:00:00', 'Asia/Riyadh'));

    expect(fn () => app(SaveAchievement::class)->handle($actor, new Achievement, ['title' => 'عنوان', 'achieved_on' => '2026-11-03', 'details' => 'تفاصيل', 'status' => 'published']))
        ->toThrow(ValidationException::class);

    $achievement = Achievement::factory()->create();
    $achievement->delete();
    expect($actor->can('update', $achievement))->toBeFalse()
        ->and(fn () => app(SaveAchievement::class)->handle($actor, $achievement, ['title' => 'عنوان', 'achieved_on' => '2026-11-02', 'details' => 'تفاصيل', 'status' => 'published']))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($actor);
    Livewire::test(ListAchievements::class)
        ->filterTable('trashed', true)
        ->callAction(TestAction::make(RestoreAction::class)->table($achievement));

    expect($achievement->fresh()->trashed())->toBeFalse()->and($actor->can('update', $achievement->fresh()))->toBeTrue();
});

it('shows visitors the published achievements only, newest first, on the public page', function () {
    Achievement::factory()->published()->create(['title' => 'إنجاز قديم', 'achieved_on' => '2025-03-01']);
    Achievement::factory()->published()->create(['title' => 'إنجاز حديث', 'achieved_on' => '2026-09-01']);
    Achievement::factory()->create(['title' => 'مسودة سرية']);
    Achievement::factory()->published()->create(['title' => 'إنجاز محذوف'])->delete();

    $this->get(route('achievements.index'))->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSeeInOrder(['إنجاز حديث', 'إنجاز قديم'])
        ->assertDontSee('مسودة سرية')
        ->assertDontSee('إنجاز محذوف');

    $this->get(route('achievements.index', ['page' => 'x']))->assertRedirect(route('achievements.index'));
    $this->get(route('achievements.index', ['page' => 5]))->assertNotFound();
});

it('shows an empty state without published achievements', function () {
    Achievement::factory()->create();

    $this->get(route('achievements.index'))->assertOk()->assertSee(__('achievements.empty'));
});

it('serves a published attachment only by a signed link, and never from a public storage URL', function () {
    $achievement = storedAchievement(['status' => AchievementStatus::Published, 'title' => 'إنجاز بمرفق']);

    $page = $this->get(route('achievements.index'))->assertOk()->getContent();
    preg_match('/href="([^"]+\/attachment\?[^"]+)"/', (string) $page, $link);
    $url = html_entity_decode($link[1]);

    expect($url)->toContain('signature=')->toContain('expires=')->and($page)->not->toContain((string) $achievement->attachment);

    $this->get($url)->assertOk()->assertDownload();
    $this->get(route('achievements.attachment', $achievement))->assertForbidden();
    $this->get($url.'x')->assertForbidden();

    $this->travel(Achievement::LINK_MINUTES + 1)->minutes();
    $this->get($url)->assertForbidden();
    $this->travelBack();

    // صورة منشورة تُعرض في المتصفح
    $image = storedAchievement(['status' => AchievementStatus::Published], 'png');
    $response = $this->get((string) $image->attachmentUrl())->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/png')
        ->and((string) $response->headers->get('Content-Disposition'))->toStartWith('inline');

    // والمحذوف لا يُقدَّم
    $achievement->delete();
    $this->get($url)->assertNotFound();
});

it('keeps a draft attachment away from visitors and the board, and logs the executive administration opening it', function () {
    $draft = storedAchievement();
    $url = (string) $draft->attachmentUrl();

    $this->get($url)->assertForbidden();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get($url)->assertForbidden();
    $this->actingAs(AssemblyMembership::factory()->create()->user)->get($url)->assertForbidden();
    $this->actingAs(userWithRole(RoleName::ContentEditor))->get($url)->assertForbidden();

    $secretary = userWithRole(RoleName::Secretary);
    $this->actingAs($secretary)->get($url)->assertOk();

    $log = Activity::query()->where('event', 'file_downloaded')->whereMorphedTo('subject', $draft)->sole();
    expect($log->causer_id)->toBe($secretary->id);

    // والحساب المعطّل يُخرج ولا يصل
    $secretary->forceFill(['is_active' => false])->save();
    $this->actingAs($secretary->fresh())->get($url)->assertRedirect();
    expect(Activity::query()->where('event', 'file_downloaded')->count())->toBe(1);
});

it('counts the published achievements of this year in Riyadh time on the board portal', function () {
    // 31 ديسمبر 21:30 بتوقيت غرينتش = 1 يناير 2027 بتوقيت الرياض
    $this->travelTo(Carbon::parse('2026-12-31 21:30:00', 'UTC'));

    Achievement::factory()->published()->create(['achieved_on' => '2027-01-01']);
    Achievement::factory()->published()->create(['achieved_on' => '2027-01-01']);
    Achievement::factory()->published()->create(['achieved_on' => '2026-12-31']);
    Achievement::factory()->create(['achieved_on' => '2027-01-01']);
    Achievement::factory()->published()->create(['achieved_on' => '2027-01-01'])->delete();

    expect(Achievement::publishedThisYear())->toBe(2);

    $board = userWithRole(RoleName::BoardMember);
    $html = (string) $this->actingAs($board)->get('/assembly')->assertOk()->getContent();
    preg_match('/data-achievements-count>(\d+)</', $html, $count);

    expect($count[1] ?? null)->toBe('2');
});

it('shows the board and the general assembly the published timeline without an add button, and the secretary the add button', function () {
    Achievement::factory()->published()->create(['title' => 'إنجاز للمجلس']);
    Achievement::factory()->create(['title' => 'مسودة لا تظهر']);

    foreach ([userWithRole(RoleName::BoardMember), AssemblyMembership::factory()->create()->user] as $viewer) {
        $this->actingAs($viewer)->get('/assembly')->assertOk()
            ->assertSee(__('achievements.this_year'))
            ->assertSee('إنجاز للمجلس')
            ->assertSee('data-achievements-timeline', false)
            ->assertDontSee('مسودة لا تظهر')
            ->assertDontSee('data-achievement-add', false);
    }

    $this->actingAs(userWithRole(RoleName::Secretary))->get('/assembly')->assertOk()
        ->assertSee('data-achievement-add', false)
        ->assertSee(AchievementResource::getUrl('create'));

    // والزائر لا يرى البوابة
    auth()->logout();
    $this->get('/assembly')->assertOk()->assertDontSee('data-board-achievements', false);
});

it('links «إنجازاتنا» from the footer quick links', function () {
    $html = (string) $this->get('/')->assertOk()->getContent();
    $footer = (string) str($html)->after('data-footer-box="links"')->before('</nav>');

    expect($footer)->toContain('href="'.route('achievements.index').'"')->toContain(__('ui.footer.links.achievements'));
});

it('grants achievements permissions to the executive administration only', function () {
    foreach ([RoleName::Secretary, RoleName::ExecutiveDirector, RoleName::TechnicalAdmin] as $role) {
        $user = userWithRole($role);
        expect($user->can('create', Achievement::class))->toBeTrue()
            ->and($user->can('update', Achievement::factory()->make()))->toBeTrue();
    }

    foreach ([RoleName::BoardMember, RoleName::ContentEditor, RoleName::ProgramManager, RoleName::AssemblyMember, RoleName::Member, RoleName::SuperAdmin] as $role) {
        /** @var User $user */
        $user = userWithRole($role);
        expect($user->can('create', Achievement::class))->toBeFalse("{$role->value} can create");
    }
});
