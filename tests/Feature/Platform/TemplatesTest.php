<?php

use Illuminate\Support\Facades\File;

/*
| القاعدة الذهبية ٢: نصوص الواجهة في lang/ar لا في القوالب. يُسمح بالعربية في التعليقات فقط.
*/

it('keeps every visible text of the templates in the translation files', function () {
    $offenders = [];

    foreach (File::allFiles(resource_path('views')) as $file) {
        $code = (string) preg_replace(
            ['/\{\{--.*?--\}\}/s', '/\/\*.*?\*\//s', '/^\s*\/\/.*$/m'],
            '',
            $file->getContents(),
        );

        if (preg_match('/\p{Arabic}/u', $code) === 1) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});
