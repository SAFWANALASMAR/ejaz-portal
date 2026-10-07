<?php

/*
| نصوص المسابقات في الموقع واللوحة. بيانات كل مسابقة وأسئلتها من اللوحة.
*/

return [

    'model' => 'مسابقة',
    'plural' => 'المسابقات',
    'title' => 'المسابقات',

    'statuses' => [
        'draft' => 'مسودة',
        'upcoming' => 'قريباً',
        'open' => 'مفتوحة',
        'closed' => 'مكتملة',
    ],

    'question_types' => [
        'text' => 'نصية',
        'multiple_choice' => 'اختيار من متعدد',
        'video' => 'رفع فيديو',
    ],

    'tabs' => [
        'details' => 'البيانات',
        'criteria' => 'معايير التقييم',
        'questions' => 'بنك الأسئلة',
    ],

    'fields' => [
        'title' => 'العنوان',
        'description' => 'الوصف',
        'terms' => 'الشروط',
        'prizes' => 'الجوائز',
        'categories' => 'الفئات',
        'categories_hint' => 'يختار المتقدم فئته منها، وتظهر في لوحة المتابعة. اتركها فارغة إن لم تكن للمسابقة فئات.',
        'opens_at' => 'موعد فتح التقديم',
        'closes_at' => 'موعد إغلاق التقديم',
        'dates_hint' => 'بتوقيت الرياض. يُفتح التقديم ويُغلق آلياً في الموعدين.',
        'shuffle_questions' => 'خلط ترتيب الأسئلة لكل متقدم',
        'shuffle_options' => 'خلط ترتيب الخيارات لكل متقدم',
        'status' => 'الحالة',
        'publish_hint' => 'المسابقة المنشورة تظهر في الموقع. ولا تُنشر مسابقة بلا أسئلة.',
        'criteria' => 'المعايير',
        'criteria_hint' => 'مجموع الأوزان 100. ويُدخل المحكّم درجة كل معيار حتى درجته العظمى.',
        'criterion_name' => 'المعيار',
        'weight' => 'الوزن (%)',
        'max_score' => 'الدرجة العظمى',
        'questions' => 'الأسئلة',
        'type' => 'النوع',
        'prompt' => 'نص السؤال',
        'is_required' => 'إلزامي',
        'options' => 'الخيارات',
        'option_label' => 'الخيار',
        'is_correct' => 'الإجابة الصحيحة',
        'points' => 'درجة السؤال',
        'max_video_seconds' => 'أقصى مدة للفيديو (بالثواني)',
        'max_video_mb' => 'أقصى حجم للفيديو (بالميجابايت)',
        'questions_count' => 'الأسئلة',
    ],

    // أسماء الحقول في رسائل التحقق من الإجراء (SaveCompetition)
    'attributes' => [
        'title' => 'العنوان',
        'slug' => 'المعرّف في الرابط',
        'opens_at' => 'موعد فتح التقديم',
        'closes_at' => 'موعد إغلاق التقديم',
        'criteria.*.name' => 'اسم المعيار',
        'criteria.*.weight' => 'وزن المعيار',
        'criteria.*.max_score' => 'الدرجة العظمى للمعيار',
        'questions.*.type' => 'نوع السؤال',
        'questions.*.prompt' => 'نص السؤال',
        'questions.*.options.*.label' => 'نص الخيار',
        'questions.*.points' => 'درجة السؤال',
        'questions.*.max_video_seconds' => 'أقصى مدة للفيديو',
        'questions.*.max_video_mb' => 'أقصى حجم للفيديو',
    ],

    'validation' => [
        'weights_total' => 'مجموع أوزان المعايير يجب أن يكون 100.',
        'choices' => 'السؤال رقم :number يحتاج خيارين على الأقل، وإجابة صحيحة واحدة فقط.',
        'video_limits' => 'السؤال رقم :number يحتاج أقصى مدة وأقصى حجم للفيديو.',
        'needs_questions' => 'لا تُنشر مسابقة بلا أسئلة.',
    ],

];
