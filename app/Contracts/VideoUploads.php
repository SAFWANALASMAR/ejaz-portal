<?php

namespace App\Contracts;

use App\Exceptions\VideoRejected;
use App\Models\SubmissionVideo;

/**
 * رفع مقاطع المشاركات مجزأً قابلاً للاستئناف: روابط مؤقتة لكل جزء، والناقص منها للاستئناف، ثم ملف
 * محلي للفحص في الطابور، ثم حفظه في القرص الخاص. وتطبيقان (config/competitions.php): عبر خادم
 * التطبيق (ChunkedVideoUpload)، أو مباشرة من المتصفح إلى تخزين متوافق مع S3 (S3VideoUploads).
 */
interface VideoUploads
{
    /**
     * يجهّز الرفع لمقطع حُفظ بمعرّفه الجديد.
     */
    public function begin(SubmissionVideo $video): void;

    /**
     * روابط رفع الأجزاء المطلوبة، مفتاحها رقم الجزء من صفر.
     *
     * @param  list<int>  $indexes
     * @return array<int, string>
     */
    public function urls(SubmissionVideo $video, array $indexes): array;

    /**
     * الأجزاء الناقصة أو المخالفة لحجمها المتوقع.
     *
     * @return list<int>
     */
    public function missing(SubmissionVideo $video): array;

    /**
     * المقطع كاملاً في ملف مؤقت محلي للفحص، والمستدعي يغلقه.
     *
     * @return resource
     *
     * @throws VideoRejected المستلم لا يطابق حجمه المعلن، فلا يُنزَّل ولا يُكتب على القرص
     */
    public function open(SubmissionVideo $video);

    /**
     * يحفظ المقطع المفحوص باسم عشوائي في القرص الخاص ويعيد مساره.
     *
     * @param  resource  $file  الملف الذي أعاده open()
     */
    public function keep(SubmissionVideo $video, $file): string;

    /**
     * يحذف بقايا الرفع (الأجزاء)، لا المقطع المحفوظ.
     */
    public function discard(SubmissionVideo $video): void;

    /**
     * يحذف بقايا رفع لا مقطع نشط لها، ويعيد عددها.
     */
    public function pruneOrphans(): int;
}
