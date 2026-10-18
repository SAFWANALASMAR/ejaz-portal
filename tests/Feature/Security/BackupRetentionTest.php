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
        ->toContain('COPY+=(--ignore-times)')
        ->toContain('RCLONE_CONFIG_VAULT_REMOTE="backup:$BACKUP_BUCKET/files"');
});

// decisions.md (19 أكتوبر 2026): Hetzner لا يدعم إلا SSE-C، والملفات تُشفَّر عند العميل، والمفتاح بلا حذف
it('encrypts the files on the client, never asks for SSE and never deletes from the backups bucket', function () {
    $commands = (string) preg_replace('/^\s*#.*$/m', '', (string) file_get_contents(base_path('scripts/backup.sh')));

    expect($commands)
        ->toContain('RCLONE_CONFIG_VAULT_TYPE=crypt')
        ->toContain('rclone copy')
        ->toContain('"vault:s3-private"')
        ->not->toContain('--sse')
        ->not->toContain('rclone sync')
        ->not->toMatch('/--delete|rclone (delete|purge|move)|\baws s3 (sync|rm|mv)\b/');
});

it('refuses to copy files without the encryption passwords, and keeps them out of the example file', function () {
    $script = (string) file_get_contents(base_path('scripts/backup.sh'));
    $example = (string) file_get_contents(base_path('.env.backup.example'));

    expect($script)->toContain('for var in BACKUP_CRYPT_PASSWORD BACKUP_CRYPT_SALT')
        ->and($example)->toMatch('/^BACKUP_CRYPT_PASSWORD=$/m')
        ->toMatch('/^BACKUP_CRYPT_SALT=$/m')
        ->toMatch('/^BACKUP_SOURCE_SECRET_ACCESS_KEY=$/m');
});

it('installs a pinned rclone checked against its published sha256 in the backup image', function () {
    $dockerfile = (string) file_get_contents(base_path('deploy/docker/Dockerfile'));

    expect($dockerfile)->toMatch('/ARG RCLONE_VERSION=v\d+\.\d+\.\d+/')
        ->toMatch('/ARG RCLONE_SHA256_AMD64=[0-9a-f]{64}/')
        ->toContain('sha256sum -c -');
});

it('asks the association to state the backup retention in the privacy policy', function () {
    expect((string) file_get_contents(base_path('docs/open-questions.md')))
        ->toContain('مدة الاحتفاظ بالنسخ الاحتياطية في سياسة الخصوصية');
});
