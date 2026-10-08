<?php

namespace Database\Factories;

use App\Enums\SurveyAudience;
use App\Enums\SurveyQuestionType;
use App\Models\Survey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Survey>
 */
class SurveyFactory extends Factory
{
    /**
     * استبيان منشور مفتوح لأصحاب الحسابات. والحالات تضيف الأسئلة أو تسمح لغير المسجلين.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => null,
            'audience' => SurveyAudience::Beneficiaries,
            'allow_guests' => false,
            'opens_at' => null,
            'closes_at' => null,
            'is_published' => true,
        ];
    }

    public function guests(): static
    {
        return $this->state(['allow_guests' => true, 'guest_limit' => 1000]);
    }

    /**
     * مقياس إلزامي، واختيار إلزامي بثلاثة خيارات، ونص اختياري.
     */
    public function withQuestions(): static
    {
        return $this->afterCreating(function (Survey $survey): void {
            $survey->questions()->createMany([
                ['type' => SurveyQuestionType::Scale, 'prompt' => 'ما مدى رضاك؟', 'is_required' => true, 'sort_order' => 1],
                ['type' => SurveyQuestionType::Choice, 'prompt' => 'كيف عرفت البرنامج؟', 'options' => ['الموقع', 'صديق', 'وسائل التواصل'], 'is_required' => true, 'sort_order' => 2],
                ['type' => SurveyQuestionType::Text, 'prompt' => 'ملاحظاتك', 'is_required' => false, 'sort_order' => 3],
            ]);
        });
    }
}
