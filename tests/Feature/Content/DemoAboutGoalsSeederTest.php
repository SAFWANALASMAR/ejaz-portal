<?php

use App\Models\Page;
use Database\Seeders\DemoAboutGoalsSeeder;

/*
| مقطع «أهداف الجمعية» التجريبي لاختبار المرساة في المتصفح (about-anchors.spec.mjs — المشرف على طلب الدمج 183، المنخفضة ٢):
| يُضاف مرة واحدة في آخر صفحة «عن الجمعية» بنص علامة «ناقص»، ولا يمس مقطعاً قائماً، وللمحلية والاختبار وحدهما.
*/

it('appends the demo goals section once, at the end, keeping the existing blocks', function () {
    $existing = ['type' => 'text', 'data' => ['heading' => 'رؤيتنا', 'body' => '<p>نص قائم</p>']];
    $page = Page::factory()->create(['slug' => Page::ABOUT, 'blocks' => [$existing]]);

    $this->seed(DemoAboutGoalsSeeder::class);
    $this->seed(DemoAboutGoalsSeeder::class);

    $blocks = $page->fresh()->blocks;
    expect($blocks)->toHaveCount(2)
        ->and($blocks[0])->toEqual($existing)
        ->and($blocks[1]['data']['heading'])->toBe(DemoAboutGoalsSeeder::HEADING)
        ->and($blocks[1]['data']['body'])->toContain(e(__('content.about.demo_goals')));
});

it('leaves a page that already has the goals section alone, and does nothing without the about page', function () {
    $goals = ['type' => 'cards', 'data' => ['heading' => DemoAboutGoalsSeeder::HEADING]];
    $page = Page::factory()->create(['slug' => Page::ABOUT, 'blocks' => [$goals]]);

    $this->seed(DemoAboutGoalsSeeder::class);
    expect($page->fresh()->blocks)->toEqual([$goals]);

    $page->forceDelete();
    $this->seed(DemoAboutGoalsSeeder::class);
    expect(Page::query()->where('slug', Page::ABOUT)->exists())->toBeFalse();
});

it('refuses to run outside the local and testing environments', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new DemoAboutGoalsSeeder)->run())->toThrow(RuntimeException::class);
});
