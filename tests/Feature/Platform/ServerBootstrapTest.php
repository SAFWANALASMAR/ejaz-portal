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

    // التحقق من مفتاح المستخدم ومن كلمة مرور sudo يسبقان كتابة إعداد SSH، فلا يُغلق الخادم على صاحبه
    $keyCheck = strpos($script, 'لا مفتاح SSH للمستخدم');
    $passwordCheck = strpos($script, 'password_set || fail');
    $sshConfig = strpos($script, '/etc/ssh/sshd_config.d/00-ejaz.conf <<');

    expect($keyCheck)->not->toBeFalse()
        ->and($passwordCheck)->not->toBeFalse()
        ->and($sshConfig)->not->toBeFalse()
        ->and($keyCheck)->toBeLessThan($sshConfig)
        ->and($passwordCheck)->toBeLessThan($sshConfig);
});

it('refuses an ADMIN_CIDR that leaves out the current session before changing anything', function () {
    $script = serverBootstrap();

    $cidrCheck = strpos($script, 'ip in net');
    $firstChange = strpos($script, '"${APT[@]}" update');

    expect($cidrCheck)->not->toBeFalse()
        ->and($firstChange)->not->toBeFalse()
        ->and($cidrCheck)->toBeLessThan($firstChange)
        ->and($script)->toContain('ipv4_mapped');
});

it('trusts the Docker repository key only by its published fingerprint', function () {
    $script = serverBootstrap();

    $fingerprintCheck = strpos($script, 'grep -qx "$DOCKER_KEY_FPR"');
    $repository = strpos($script, '/etc/apt/sources.list.d/docker.list');

    expect($script)->toContain('DOCKER_KEY_FPR=9DC858229FC7DD38854AE2D88D81803C0EBFCD88')
        ->and($fingerprintCheck)->not->toBeFalse()
        ->and($repository)->not->toBeFalse()
        ->and($fingerprintCheck)->toBeLessThan($repository);
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
