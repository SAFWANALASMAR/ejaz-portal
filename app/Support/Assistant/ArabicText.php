<?php

namespace App\Support\Assistant;

/**
 * تطبيع النص العربي للبحث في فهرس المساعد: السؤال والمحتوى بالقواعد نفسها، فيتطابقان مهما اختلف الرسم.
 * - يحذف التشكيل والتطويل، ويوحّد الهمزات (أ إ آ ٱ ← ا، ؤ ← و، ئ ← ي)، والتاء المربوطة هاءً، والألف المقصورة ياءً.
 * - يحوّل الأرقام العربية الهندية إلى لاتينية، ويحذف ما ليس حرفاً أو رقماً.
 * - يجرّد السوابق الشائعة (و، ال، بال، كال، فال، لل) من الكلمة الطويلة وحدها، فتطابق «البرامج» «برامج».
 * وتجريد اللواحق متروك لمطابقة البادئة في الاستعلام (AssistantRetriever)، فـ«سجل» تطابق «سجلات».
 */
final class ArabicText
{
    /**
     * كلمات السؤال التي لا تميّز موضوعه، فلا تُبحث (تجعل كل مقطع مطابقاً).
     */
    private const STOP_WORDS = [
        'في', 'من', 'الي', 'علي', 'عن', 'مع', 'او', 'ثم', 'هل', 'ما', 'ماذا', 'لماذا', 'كيف', 'كيفيه', 'متي', 'اين', 'وين',
        'هو', 'هي', 'هم', 'انا', 'انت', 'نحن', 'هذا', 'هذه', 'ذلك', 'تلك', 'التي', 'الذي', 'الذين', 'كان', 'يكون', 'لا',
        'نعم', 'لم', 'لن', 'قد', 'كل', 'اي', 'اذا', 'ان', 'انه', 'عند', 'عندي', 'لي', 'لك', 'به', 'بها', 'فيه', 'فيها',
        'منه', 'منها', 'عليه', 'عليها', 'ابغي', 'ابي', 'اريد', 'ممكن', 'لو', 'سمحت', 'شكرا', 'يا', 'شو', 'وش', 'ايش',
        'حتي', 'بعد', 'قبل', 'غير', 'بين', 'لكن', 'بل', 'يمكن', 'يمكنني', 'استطيع',
    ];

    private const PREFIXES = ['وال', 'بال', 'كال', 'فال', 'لل', 'ال', 'و'];

    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        // التشكيل (ومنه علامات القرآن الصغيرة) والتطويل
        $text = (string) preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u', '', $text);
        $text = strtr($text, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ؤ' => 'و', 'ئ' => 'ي', 'ى' => 'ي', 'ة' => 'ه',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return implode(' ', array_map(self::stem(...), self::words($text)));
    }

    /**
     * كلمات السؤال المميِّزة بعد التطبيع، بلا تكرار ولا كلمات شائعة، وبحد أقصى.
     *
     * @return list<string>
     */
    public static function terms(string $question, int $limit = 12): array
    {
        $words = self::words(self::normalize($question));
        $stops = array_map(self::stem(...), self::STOP_WORDS);
        $terms = array_filter($words, fn (string $word): bool => mb_strlen($word) > 1 && ! in_array($word, $stops, true));

        return array_slice(array_values(array_unique($terms)), 0, $limit);
    }

    /**
     * @return list<string>
     */
    private static function words(string $text): array
    {
        return array_values(array_filter(explode(' ', trim($text)), fn (string $word): bool => $word !== ''));
    }

    private static function stem(string $word): string
    {
        foreach (self::PREFIXES as $prefix) {
            // يبقى بعد التجريد ثلاثة أحرف على الأقل، فلا تصير «ولد» «لد» ولا «الف» «ف»
            if (str_starts_with($word, $prefix) && mb_strlen($word) - mb_strlen($prefix) >= 3) {
                return mb_substr($word, mb_strlen($prefix));
            }
        }

        return $word;
    }
}
