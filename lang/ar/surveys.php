<?php

/*
| نصوص قياس الرضا والإحصائيات. الاستبيانات وأسئلتها من اللوحة.
*/

return [

    'model' => 'استبيان',
    'plural' => 'قياس الرضا',

    'audiences' => [
        'beneficiaries' => 'المستفيدون',
        'trainees' => 'المتدربون',
        'members' => 'الأعضاء',
        'partners' => 'الشركاء',
        'general' => 'عام',
    ],

    'question_types' => [
        'scale' => 'مقياس من 1 إلى 5',
        'choice' => 'اختيار',
        'text' => 'نص',
    ],

    'tabs' => [
        'details' => 'البيانات',
        'questions' => 'الأسئلة',
    ],

    'fields' => [
        'title' => 'العنوان',
        'description' => 'الوصف',
        'audience' => 'الفئة المستهدفة',
        'competition' => 'المسابقة المرتبطة',
        'competition_hint' => 'اتركه فارغاً للاستبيان العام. والربط بالبرامج يُضاف مع وحدتها.',
        'allow_guests' => 'يُجيب غير المسجلين برابط الاستبيان',
        'allow_guests_hint' => 'صاحب الحساب يُجيب مرة واحدة. وغير المسجل يُمنع تكراره من جهازه فقط، فلا يُعتمد عليه في القرارات الدقيقة.',
        'opens_at' => 'يُفتح في (اختياري)',
        'closes_at' => 'يُغلق في (اختياري)',
        'link' => 'رابط الاستبيان',
        'questions' => 'الأسئلة',
        'type' => 'النوع',
        'prompt' => 'نص السؤال',
        'is_required' => 'إلزامي',
        'options' => 'الخيارات',
        'option_label' => 'الخيار',
        'responses' => 'الإجابات',
        'questions_locked' => 'الأسئلة مقفلة لأن للاستبيان إجابات.',
    ],

    'attributes' => [
        'title' => 'العنوان',
        'description' => 'الوصف',
        'competition_id' => 'المسابقة المرتبطة',
        'opens_at' => 'موعد الفتح',
        'allow_guests' => 'السماح لغير المسجلين',
        'questions.*.options' => 'الخيارات',
        'audience' => 'الفئة المستهدفة',
        'closes_at' => 'موعد الإغلاق',
        'questions.*.type' => 'نوع السؤال',
        'questions.*.prompt' => 'نص السؤال',
        'questions.*.options.*.label' => 'نص الخيار',
    ],

    'validation' => [
        'choices' => 'السؤال رقم :number يحتاج خيارين على الأقل.',
        'needs_questions' => 'لا يُنشر استبيان بلا أسئلة.',
        'questions_locked' => 'لا تُعدَّل الأسئلة بعد أول إجابة.',
    ],

    'results' => [
        'action' => 'النتائج',
        'title' => 'نتائج: :survey',
        'responses' => 'عدد الإجابات: :count',
        'answered' => 'أجاب :count',
        'average' => 'المتوسط: :average من 5',
        'no_answers' => 'لا إجابات بعد.',
        'export' => 'تصدير النتائج',
        'csv_question' => 'السؤال',
        'csv_type' => 'النوع',
        'csv_answer' => 'الإجابة',
        'csv_count' => 'العدد',
        'csv_average' => 'المتوسط',
        'texts_limit' => 'تُعرض أحدث :count إجابة نصية، وكلها في التصدير.',
    ],

    'public' => [
        'intro' => 'رأيك يساعد الجمعية على تحسين برامجها وخدماتها.',
        'question' => 'السؤال :number',
        'optional' => 'اختياري',
        'scale_low' => 'غير راضٍ',
        'scale_high' => 'راضٍ جداً',
        'submit' => 'إرسال',
        'thanks' => 'شكراً لك، وصلت إجابتك.',
        'already' => 'سبق أن أجبت على هذا الاستبيان.',
        'unavailable' => 'هذا الاستبيان غير متاح الآن.',
        'not_audience' => 'هذا الاستبيان لفئة محددة: أعضاء الجمعية العمومية، أو المشاركين في المسابقة المرتبطة به.',
        'login' => 'سجّل الدخول للإجابة على هذا الاستبيان.',
    ],

    'stats' => [
        'heading' => 'إحصائيات البوابة',
        'users' => 'الحسابات',
        'verified_users' => 'موثّقو الجوال',
        'new_users' => 'حسابات جديدة (30 يوماً)',
        'competitions' => 'المسابقات المنشورة',
        'submissions' => 'المشاركات المرسلة',
        'drafts' => 'مسودات المشاركات',
        'surveys' => 'الاستبيانات المنشورة',
        'responses' => 'إجابات الاستبيانات',
        'updated' => 'آخر تحديث: :time',
        'not_ready' => 'تُحسب الإحصائيات في الخلفية، وتظهر بعد دقائق.',
    ],

];
