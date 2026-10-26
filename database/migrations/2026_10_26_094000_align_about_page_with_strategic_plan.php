<?php

use App\Support\ContentCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * صفحة «عن الجمعية» القائمة في القاعدة على «الخطة الاستراتيجية 1447» (مراجعة المشرف، 26 أكتوبر 2026): الرؤية والرسالة (ص8)
 * والقيم الأربع (ص9 و10) والأهداف الخمسة (ص11) حرفياً، ويُحذف مقطع «التوجه الاستراتيجي» الفارغ من الموقع القديم، فما في
 * الخطة هو التوجه نفسه. ويبقى «من نحن» وصوره كما هي، فلا يخالف الخطة. وملف الاستيراد على النص نفسه (legacy-site.json).
 *
 * يُحفظ ما كان قبله في الإعدادات (about_page_before_plan)، فيعيده الرجوع كما كان.
 */
return new class extends Migration
{
    private const BACKUP = 'about_page_before_plan';

    private const VISION = '<p>ريادة تعليمية في ضوء براهين الإيمان العلمية.</p>';

    private const MISSION = '<p>الإسهام في تنمية وتطوير المنظومة التعليمية والبحثية لبراهين الإيمان العلمية، عبر مبادرات نوعية، وشراكات فاعلة، وبرامج ودراسات متخصصة، وفق معايير منضبطة وموثوقة.</p>';

    private const GOALS = [
        'تعزيز القيم والانتماء الوطني.',
        'تجويد نواتج التعلم وتحسين موقع النظام التعليمي عالمياً.',
        'تنمية وتطوير قُدرات الكوادر التعليمية.',
        'تطوير منظومة الجامعات والمؤسسات التعليمية والتدريبية.',
        'تعزيز مشاركة المُجتمع في التعليم والتعلم.',
    ];

    private const VALUES = [
        ['الإحسان والمهنية', 'نسعى إلى التمسك بأعلى معايير المهنية والقيمية.'],
        ['الابتكار', 'تبني الابتكار المؤدي إلى التميز لإيجاد بيئة جاذبة ومحفزة للإبداع.'],
        ['التمكين', 'تمكين الباحثين والكوادر العلمية والتعليمية قيمة وركيزة أساسية نعتز بها.'],
        ['التكامل', 'التشاركية والتواصل مع الأطراف ذات العلاقة والاستثمار الأمثل للإمكانات وتكامل الطاقات.'],
    ];

    public function up(): void
    {
        $page = DB::table('pages')->where('slug', 'about')->first();

        if ($page === null || DB::table('settings')->where('key', self::BACKUP)->exists()) {
            return;
        }

        $blocks = json_decode((string) $page->blocks, true);
        $blocks = is_array($blocks) ? array_values($blocks) : [];

        DB::table('settings')->insert(['key' => self::BACKUP, 'value' => json_encode(['blocks' => $blocks], JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now()]);

        $blocks = array_values(array_filter($blocks, fn (mixed $block): bool => self::heading($block) !== 'التوجه الاستراتيجي'));

        $blocks = self::put($blocks, 'الرؤية', ['type' => 'text', 'data' => ['heading' => 'الرؤية', 'body' => self::VISION]],
            fn (array $block): array => [...$block, 'data' => [...$block['data'], 'body' => self::VISION]]);
        $blocks = self::put($blocks, 'الرسالة', ['type' => 'text', 'data' => ['heading' => 'الرسالة', 'body' => self::MISSION]],
            fn (array $block): array => [...$block, 'data' => [...$block['data'], 'body' => self::MISSION]]);
        $blocks = self::put($blocks, 'أهداف الجمعية', self::cards('أهداف الجمعية', array_map(fn (string $goal): array => [$goal, null], self::GOALS), []),
            fn (array $block): array => self::cards('أهداف الجمعية', array_map(fn (string $goal): array => [$goal, null], self::GOALS), (array) ($block['data']['items'] ?? [])));
        $blocks = self::put($blocks, 'قيم الجمعية', self::cards('قيم الجمعية', self::VALUES, []),
            fn (array $block): array => self::cards('قيم الجمعية', self::VALUES, (array) ($block['data']['items'] ?? [])));

        DB::table('pages')->where('id', $page->id)->update(['blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        ContentCache::flush();
    }

    public function down(): void
    {
        $backup = DB::table('settings')->where('key', self::BACKUP)->value('value');
        $before = is_string($backup) ? json_decode($backup, true) : null;

        if (is_array($before) && is_array($before['blocks'] ?? null)) {
            DB::table('pages')->where('slug', 'about')->update(['blocks' => json_encode($before['blocks'], JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        }

        DB::table('settings')->where('key', self::BACKUP)->delete();
        ContentCache::flush();
    }

    private static function heading(mixed $block): ?string
    {
        $heading = is_array($block) ? ($block['data']['heading'] ?? null) : null;

        return is_string($heading) ? $heading : null;
    }

    /**
     * يعدّل المقطع بعنوانه، أو يضيفه آخر الصفحة إن لم يكن.
     *
     * @param  list<mixed>  $blocks
     * @param  array<string, mixed>  $new
     * @param  Closure(array<string, mixed>): array<string, mixed>  $update
     * @return list<mixed>
     */
    private static function put(array $blocks, string $heading, array $new, Closure $update): array
    {
        foreach ($blocks as $index => $block) {
            if (self::heading($block) === $heading && is_array($block)) {
                $blocks[$index] = $update($block);

                return $blocks;
            }
        }

        return [...$blocks, $new];
    }

    /**
     * مقطع بطاقات بعناوين الخطة ونصوصها، وتبقى صور البطاقات وأيقوناتها القائمة بترتيبها.
     *
     * @param  list<array{0: string, 1: ?string}>  $items
     * @param  array<int, mixed>  $existing
     * @return array<string, mixed>
     */
    private static function cards(string $heading, array $items, array $existing): array
    {
        $cards = [];

        foreach ($items as $index => [$title, $text]) {
            $old = is_array($existing[$index] ?? null) ? $existing[$index] : [];
            $cards[] = [...$old, 'icon' => $old['icon'] ?? null, 'title' => $title, 'text' => $text];
        }

        return ['type' => 'cards', 'data' => ['heading' => $heading, 'items' => $cards]];
    }
};
