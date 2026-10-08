<?php

namespace Database\Factories;

use App\Models\FormTemplate;
use App\Models\FormTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * نموذج بإصداره الأول، بحقول تجريبية من كل نوع غير الملفات.
 *
 * @extends Factory<FormTemplate>
 */
class FormTemplateFactory extends Factory
{
    /**
     * @var list<array<string, mixed>>
     */
    public const FIELDS = [
        ['key' => 'name', 'label' => 'الاسم', 'type' => 'text', 'required' => true],
        ['key' => 'notes', 'label' => 'ملاحظات', 'type' => 'long_text', 'required' => false],
        ['key' => 'amount', 'label' => 'المبلغ', 'type' => 'number', 'required' => false],
        ['key' => 'day', 'label' => 'التاريخ', 'type' => 'date', 'required' => false],
        ['key' => 'kind', 'label' => 'النوع', 'type' => 'select', 'required' => false, 'options' => ['أ', 'ب']],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'نموذج تجريبي '.fake()->unique()->numberBetween(1, 9999),
            'description' => null,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (FormTemplate $template): void {
            if ($template->current_version_id !== null) {
                return;
            }

            $version = new FormTemplateVersion;
            $version->form_template_id = $template->id;
            $version->version = 1;
            $version->fields = self::FIELDS;
            $version->save();

            $template->forceFill(['current_version_id' => $version->id])->saveQuietly();
        });
    }
}
