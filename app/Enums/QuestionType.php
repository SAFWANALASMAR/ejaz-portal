<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * أنواع أسئلة المسابقة (requirements/06): نصية، واختيار من متعدد يُصحَّح آلياً، ورفع فيديو. ولمسابقة الطلاب
 * (plans/student-competition.md): الاختيار على نص مقروء أو مقطع يوتيوب، وصح وخطأ، والمقالي (مطفأ حتى يُفعّل).
 */
enum QuestionType: string implements HasLabel
{
    case Text = 'text';
    case MultipleChoice = 'multiple_choice';
    case Video = 'video';
    case PassageChoice = 'passage_choice';
    case VideoChoice = 'video_choice';
    case TrueFalse = 'true_false';
    case Essay = 'essay';

    public function getLabel(): string
    {
        return __('competitions.question_types.'.$this->value);
    }

    /**
     * أنواع مسابقة الأعمال والاختبار الإلكتروني (بنك الأسئلة في نموذج المسابقة).
     *
     * @return list<self>
     */
    public static function classic(): array
    {
        return [self::Text, self::MultipleChoice, self::Video];
    }

    /**
     * أنواع مسابقة الطلاب، والمقالي حين يُفعَّل وحده.
     *
     * @return list<self>
     */
    public static function student(): array
    {
        $types = [self::MultipleChoice, self::PassageChoice, self::VideoChoice, self::TrueFalse];

        if ((bool) config('competitions.student.essay_enabled')) {
            $types[] = self::Essay;
        }

        return $types;
    }

    /**
     * يُصحَّح آلياً بمطابقة الخيار الصحيح.
     */
    public function isChoice(): bool
    {
        return in_array($this, [self::MultipleChoice, self::PassageChoice, self::VideoChoice, self::TrueFalse], true);
    }

    /**
     * نوع النص أو المقطع الذي يلزم السؤال، أو null للسؤال المنفرد.
     */
    public function passageKind(): ?PassageKind
    {
        return match ($this) {
            self::PassageChoice => PassageKind::Text,
            self::VideoChoice => PassageKind::Video,
            default => null,
        };
    }
}
