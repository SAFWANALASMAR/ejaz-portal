<?php

use Illuminate\Support\Facades\File;

/*
| خطوات نشر التحديث البصري 2 (مراجعة المشرف على #111، 1 نوفمبر 2026 — المنخفضتان 5 و6):
| - بذرة القائمة بسبعة عناصر في سكربت النشر ودليله، مرة واحدة بعلامة على الخادم، فلا تعيد كل نشر ترتيب ما رتّبه المحرر.
| - وما تغيّر في «إعدادات الموقع» (مفتاحا البطل والاختصارات، وإظهار الشركاء افتراضياً) مكتوب في دليل النشر.
*/

it('runs the seven item menu seeder once from the deploy script, after the access seeder', function () {
    $script = File::get(base_path('scripts/deploy-compose.sh'));

    expect($script)->toContain('db:seed --class=MainMenuSevenSeeder --force')
        ->toContain('if [ ! -f .deploy-state/main-menu-seven ]; then')
        ->toContain('touch .deploy-state/main-menu-seven')
        ->and(strpos($script, 'MainMenuSevenSeeder'))->toBeGreaterThan(strpos($script, '--class=AccessSeeder'))
        ->and(strpos($script, 'MainMenuSevenSeeder'))->toBeLessThan(strpos($script, 'up -d --wait --remove-orphans'))
        ->and(File::get(base_path('.gitignore')))->toContain('/.deploy-state');
});

it('documents the menu seeder and the site settings changes in the deploy guide', function () {
    $guide = File::get(base_path('docs/deploy.md'));

    expect($guide)->toContain('db:seed --class=MainMenuSevenSeeder --force')
        ->toContain('.deploy-state/main-menu-seven')
        ->toContain('`show_hero`')
        ->toContain('`show_shortcuts`')
        ->toContain('`show_partners`');
});
