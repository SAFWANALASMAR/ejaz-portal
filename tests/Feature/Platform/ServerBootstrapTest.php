<?php

/*
| سكربت تجهيز الخادم (scripts/server-bootstrap.sh، docs/deploy.md «Hostinger KVM»): يُشغَّل بـ root على خادم الإنتاج،
| فالفحص الآلي يمرره على shellcheck، وهذه تثبّت ضماناته فلا تسقط بتعديل لاحق.
*/

function serverBootstrap(): string
{
    return (string) file_get_contents(base_path('scripts/server-bootstrap.sh'));
}

it('lints the server bootstrap script in CI', function () {
    expect((string) file_get_contents(base_path('.github/workflows/ci.yml')))
        ->toContain('shellcheck --severity=style scripts/server-bootstrap.sh');
});

it('allows SSH by key alone and only after the admin user has a key', function () {
    $script = serverBootstrap();

    expect($script)->toContain('set -euo pipefail')
        ->toContain('PermitRootLogin no')
        ->toContain('PasswordAuthentication no')
        ->toContain('AuthenticationMethods publickey')
        ->toContain('sshd -t');

    // التحقق من مفتاح المستخدم يسبق كتابة إعداد SSH، فلا يُغلق الخادم على صاحبه
    expect(strpos($script, 'لا مفتاح SSH للمستخدم'))->toBeLessThan(strpos($script, '00-ejaz.conf'));
});

it('opens 22, 80 and 443 alone and never the database or Redis', function () {
    $script = serverBootstrap();

    expect($script)->toContain('ufw default deny incoming')
        ->toContain('ufw allow 80/tcp')
        ->toContain('ufw allow 443/tcp')
        ->not->toContain('5432')
        ->not->toContain('6379');
});

it('installs fail2ban, security updates and Docker from the official repository', function () {
    expect(serverBootstrap())->toContain('fail2ban')
        ->toContain('APT::Periodic::Unattended-Upgrade "1";')
        ->toContain('https://download.docker.com/linux/ubuntu')
        ->toContain('docker-compose-plugin')
        ->toContain('/opt/ejaz');
});
