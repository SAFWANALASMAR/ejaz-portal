<?php

use App\Support\ContentCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * إزالة التكرار في صفحة «عن الجمعية» (قرار صفوان، 29 أكتوبر 2026). ترحيل 2026_10_26_094000 يبحث عن مقاطع الخطة الاستراتيجية
 * بعناوينها حرفياً، وعناوين الموقع القديم في الصفحات المستوردة قبل #66 ممطوطة («الرســالة» و«الرؤيــة»)، و«الأهداف
 * الاستراتيجية» و«قيمنا» مرادفان لـ«أهداف الجمعية» و«قيم الجمعية». فلم يجدها، وأضاف مقاطع الخطة الأربعة بعدها فتكررت.
 *
 * يُبقي مقطع الخطة بعنوانه الحرفي كما هو، ويحذف ما يقابله من القديم (بالعنوان بلا تطويل وبالمرادفين). وصور البطاقات
 * وأيقوناتها في القديم تنتقل إلى بطاقات الخطة بترتيبها إن خلت منها، فلا تضيع صور الأهداف. والترحيل لا يمس صفحة بلا تكرار.
 * ويُحفظ ما كان قبله في about_page_before_dedupe (لا about_page_before_plan: نسخة ذلك الترحيل لا يُكتب فوقها)، فيعيده الرجوع.
 */
return new class extends Migration
{
    private const BACKUP = 'about_page_before_dedupe';

    /**
     * العنوان بلا تطويل ← عنوان مقطع الخطة.
     *
     * @var array<string, string>
     */
    private const CANONICAL = [
        'الرؤية' => 'الرؤية',
        'الرسالة' => 'الرسالة',
        'أهداف الجمعية' => 'أهداف الجمعية',
        'الأهداف الاستراتيجية' => 'أهداف الجمعية',
        'قيم الجمعية' => 'قيم الجمعية',
        'قيمنا' => 'قيم الجمعية',
    ];

    public function up(): void
    {
        $page = DB::table('pages')->where('slug', 'about')->first();

        if ($page === null) {
            return;
        }

        $original = json_decode((string) $page->blocks, true);
        $blocks = is_array($original) ? array_values($original) : [];
        $changed = false;

        foreach (array_unique(self::CANONICAL) as $heading) {
            $plan = self::indexOf($blocks, $heading);

            if ($plan === null) {
                continue;
            }

            foreach ($blocks as $index => $block) {
                if ($index === $plan || self::canonical($block) !== $heading) {
                    continue;
                }

                $blocks[$plan] = self::carryImages($blocks[$plan], $block);
                unset($blocks[$index]);
                $changed = true;
            }

            $blocks = array_values($blocks);
        }

        if (! $changed) {
            return;
        }

        DB::table('settings')->updateOrInsert(['key' => self::BACKUP], ['value' => json_encode(['blocks' => $original], JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now()]);
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

    /**
     * أول مقطع بالعنوان الحرفي (مقطع الخطة).
     *
     * @param  list<mixed>  $blocks
     */
    private static function indexOf(array $blocks, string $heading): ?int
    {
        foreach ($blocks as $index => $block) {
            if (is_array($block) && ($block['data']['heading'] ?? null) === $heading) {
                return $index;
            }
        }

        return null;
    }

    private static function canonical(mixed $block): ?string
    {
        $heading = is_array($block) ? ($block['data']['heading'] ?? null) : null;

        if (! is_string($heading)) {
            return null;
        }

        return self::CANONICAL[trim(str_replace('ـ', '', $heading))] ?? null;
    }

    /**
     * صورة كل بطاقة قديمة وأيقونتها إلى بطاقة الخطة في موضعها، إن خلت منهما.
     *
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private static function carryImages(array $plan, mixed $old): array
    {
        $oldItems = is_array($old) ? (array) ($old['data']['items'] ?? []) : [];

        if (($plan['type'] ?? null) !== 'cards' || $oldItems === []) {
            return $plan;
        }

        $items = (array) ($plan['data']['items'] ?? []);

        foreach ($items as $index => $item) {
            $source = is_array($oldItems[$index] ?? null) ? $oldItems[$index] : [];

            // picture: صورة البطاقة المخزنة (ContentBlocks::images)
            foreach (['picture', 'icon'] as $key) {
                if (is_array($item) && blank($item[$key] ?? null) && filled($source[$key] ?? null)) {
                    $items[$index][$key] = $source[$key];
                }
            }
        }

        $plan['data']['items'] = $items;

        return $plan;
    }
};
