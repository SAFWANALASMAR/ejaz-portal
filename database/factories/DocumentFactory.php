<?php

namespace Database\Factories;

use App\Enums\DocumentCategory;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'category' => DocumentCategory::Policies,
            'issued_at' => fake()->date(),
            'version' => null,
            'file' => Document::DIRECTORY.'/'.fake()->uuid().'.pdf',
            'file_size' => 1024,
            'description' => null,
            // الافتراض في القاعدة واللوحة «للاطلاع فقط» (البند ٢٢)، واختبارات التنزيل والعرض المدمج القائمة على وثيقة يُتاح
            // تنزيلها. والحالة viewOnly للقارئ
            'is_view_only' => false,
            'is_published' => true,
        ];
    }

    /**
     * وثيقة «للاطلاع فقط»: قارئ الصفحة وحده، بلا تنزيل (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ٢٢).
     */
    public function viewOnly(): static
    {
        return $this->state(['is_view_only' => true]);
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }

    /**
     * وثيقة برابط مباشر بدل الملف.
     */
    public function link(string $url = 'https://example.org/report'): static
    {
        return $this->state(['file' => null, 'file_size' => null, 'url' => $url]);
    }
}
