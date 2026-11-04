<?php

namespace App\Console\Commands;

use App\Enums\LibrarySection;
use App\Models\LibraryItem;
use App\Support\ContentCache;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * بحثان تجريبيان في المكتبة لرابط العرض والبيئة المحلية وحدهما (إضافة توجيه المدير التنفيذي، 3 نوفمبر 2026 — البند ٦،
 * docs/decisions/2026-11-03-director-home.md): يظهر بهما قسم الأبحاث في شريط «من مكتبة إعجاز» حين لا بحث منشوراً.
 * كل نص فيهما معلَّم «تجريبي — للعرض فقط»، بلا غلاف (فتظهر أيقونة الكتاب)، وملفهما PDF من صفحة فارغة. ولا يعمل في الإنتاج
 * ولا يضيف شيئاً إن وُجد بحث منشور.
 */
#[Signature('ejaz:demo-research')]
#[Description('إضافة بحثين تجريبيين إلى المكتبة إن لم يوجد بحث منشور (البيئة المحلية فقط)')]
class CreateDemoResearch extends Command
{
    public const SLUG_PREFIX = 'demo-research-';

    // صفحة PDF فارغة صالحة بلا نص، فلا محتوى في الملف
    private const BLANK_PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

    public function handle(): int
    {
        if (! $this->laravel->environment('local', 'testing')) {
            $this->error(__('home.demo_research.refused'));

            return self::FAILURE;
        }

        if (LibraryItem::query()->published()->where('section', LibrarySection::Research)->exists()) {
            $this->info(__('home.demo_research.exists'));

            return self::SUCCESS;
        }

        foreach ([1, 2] as $number) {
            $file = LibraryItem::FILE_DIRECTORY.'/'.Str::uuid().'.pdf';
            Storage::disk(LibraryItem::disk())->put($file, self::BLANK_PDF);

            LibraryItem::query()->create([
                'slug' => self::SLUG_PREFIX.$number,
                'title' => __('home.demo_research.title', ['number' => $number]),
                'section' => LibrarySection::Research,
                'description' => __('home.demo_research.description'),
                'published_on' => now()->toDateString(),
                'tags' => [],
                'file' => $file,
                'file_size' => strlen(self::BLANK_PDF),
                'is_published' => true,
            ]);
        }

        ContentCache::flush();
        $this->info(__('home.demo_research.created'));

        return self::SUCCESS;
    }
}
