<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

/*
| نتائج المدقق اللغوي (fix/v0.15-arabic-copy): رسائل التحقق تتفق مع الحقل بلفظ «حقل» و«عدد أحرف» و«عدد عناصر»،
| وأسماء حقول المسابقة، ورسالة الصلاحية، وعلامة الناقص في الترويسة لا تُطبع في الإنتاج، ولا اسم متغير بيئة في نص.
*/

function arabicCopyError(array $data, array $rules): string
{
    return (string) Validator::make($data, $rules)->errors()->first();
}

it('words the required family with «حقل» so the attribute gender never clashes', function () {
    expect(arabicCopyError([], ['password' => 'required']))->toBe('حقل كلمة المرور مطلوب.')
        ->and(__('validation.required', ['attribute' => __('competitions.entry.category')]))->toBe('حقل الفئة مطلوب.')
        ->and(arabicCopyError(['name' => 'x'], ['name' => 'prohibited']))->toBe('حقل الاسم غير مسموح به.')
        ->and(__('validation.unique', ['attribute' => __('validation.attributes.email')]))->toBe('قيمة البريد الإلكتروني مستخدمة من قبل.');
});

it('counts letters, digits and items with the counted noun first', function () {
    expect(arabicCopyError(['password' => 'abc'], ['password' => 'string|min:8']))->toBe('يجب ألا يقل عدد أحرف كلمة المرور عن 8.')
        ->and(arabicCopyError(['name' => 'abc'], ['name' => 'string|size:5']))->toBe('يجب أن يكون عدد أحرف الاسم 5.')
        ->and(arabicCopyError(['name' => 'abcdef'], ['name' => 'string|max:5']))->toBe('يجب ألا يزيد عدد أحرف الاسم على 5.')
        ->and(arabicCopyError(['code' => '12'], ['code' => 'digits:6']))->toBe('يجب أن يكون عدد أرقام الرمز 6.')
        ->and(arabicCopyError(['answers' => [1]], ['answers' => 'array|min:3']))->toBe('يجب ألا يقل عدد عناصر الإجابات عن 3.')
        ->and(arabicCopyError(['answers' => [1, 2, 3]], ['answers' => 'array|max:2']))->toBe('يجب ألا يزيد عدد عناصر الإجابات على 2.');
});

it('names the new attributes and the login identifier', function () {
    expect(__('validation.attributes.region'))->toBe('المنطقة')
        ->and(__('validation.attributes.answers'))->toBe('الإجابات')
        ->and(__('validation.attributes.login'))->toBe('اسم المستخدم أو البريد أو الجوال')
        ->and(arabicCopyError([], ['region' => 'required']))->toBe('حقل المنطقة مطلوب.');
});

it('keeps the director wording for the contact mobile, and words the permission message', function () {
    // «رقم الجوال» إلزامي للجميع بقرار المدير (29 أكتوبر 2026، BrahinDirectorNotesTest)، لا «جوال ولي الأمر»
    expect(__('competitions.student.attributes.guardian_phone'))->toBe('رقم الجوال')
        ->and(__('competitions.student.admin.not_allowed'))->toBe('لا تملك صلاحية هذه العملية.');
});

it('prints no missing marker on the form letterhead in production, and keeps it elsewhere', function () {
    $render = fn (): string => view('documents.partials.form-letterhead', [
        'letterhead' => ['name' => 'جمعية إعجاز للدراسات والبحوث العلمية', 'contact' => []],
        'logo' => '/images/brand/logo-horizontal.svg',
    ])->render();

    expect($render())->toContain(__('documents.print.missing_contact'));

    app()->detectEnvironment(fn () => 'production');

    expect($render())->not->toContain('ناقص')->not->toContain('【');
});

it('names no environment variable in any Arabic text', function () {
    $found = [];

    foreach (File::files(lang_path('ar')) as $file) {
        $lines = require $file->getPathname();
        array_walk_recursive($lines, function (mixed $value, int|string $key) use (&$found, $file): void {
            if (is_string($value) && preg_match('/\b[A-Z][A-Z0-9]*_[A-Z0-9_]+\b/', $value, $match)) {
                $found[] = $file->getFilename().':'.$key.' → '.$match[0];
            }
        });
    }

    expect($found)->toBe([]);
});
