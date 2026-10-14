<?php

/**
 * مدة الاحتفاظ بالنسخ الاحتياطية (decisions.md — 14 أكتوبر 2026): قاعدة دورة الحياة في حاوية النسخ
 * تحذف نسخ القاعدة ونسخ الملفات وإصداراتها القديمة، فلا تبقى بيانات من حُذف أو أُخفيت بياناته بلا نهاية.
 */
function backupLifecycleRules(): array
{
    $json = json_decode((string) file_get_contents(base_path('deploy/storage/lifecycle-backups.json')), true, flags: JSON_THROW_ON_ERROR);

    return collect($json['Rules'])->keyBy('ID')->all();
}

it('expires database dumps and file copies in the backups bucket', function (string $prefix) {
    $rule = collect(backupLifecycleRules())
        ->first(fn (array $rule) => ($rule['Filter']['Prefix'] ?? null) === $prefix && isset($rule['Expiration']['Days']));

    expect($rule)->not->toBeNull()
        ->and($rule['Status'])->toBe('Enabled')
        ->and($rule['Expiration']['Days'])->toBe(30);
})->with(['db/', 'files/']);

it('expires noncurrent versions of every backup object', function () {
    $rule = collect(backupLifecycleRules())->first(fn (array $rule) => isset($rule['NoncurrentVersionExpiration']));

    expect($rule)->not->toBeNull()
        ->and($rule['Status'])->toBe('Enabled')
        ->and($rule['Filter']['Prefix'])->toBe('')
        ->and($rule['NoncurrentVersionExpiration']['NoncurrentDays'])->toBe(30);
});

it('refreshes every live file weekly so an unchanged file does not expire from the backup', function () {
    $script = (string) file_get_contents(base_path('scripts/backup.sh'));

    expect($script)
        ->toContain('BACKUP_FILES_REFRESH_WEEKDAY')
        ->toContain('cp --recursive')
        ->toContain('"s3://$BACKUP_BUCKET/files/$disk/"');
});

it('asks the association to state the backup retention in the privacy policy', function () {
    expect((string) file_get_contents(base_path('docs/open-questions.md')))
        ->toContain('مدة الاحتفاظ بالنسخ الاحتياطية في سياسة الخصوصية');
});
