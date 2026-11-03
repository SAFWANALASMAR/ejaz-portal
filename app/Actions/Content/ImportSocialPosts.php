<?php

namespace App\Actions\Content;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostKind;
use App\Models\SocialPost;
use App\Models\User;
use App\Rules\PlainText;
use App\Rules\SafeUrl;
use App\Rules\SocialPostUrl;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Activitylog\Support\CauserResolver;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

/**
 * استيراد «منشوراتنا» دفعة واحدة من ملف إكسل أو CSV (طلب صفوان، 3 نوفمبر 2026): كل صف صحيح منشور مسودة، والصف المرفوض
 * يُذكر برقمه في الملف وسببه ولا يوقف غيره. والرابط لا يتكرر بعد تسويته (SocialPostUrl::normalize): لا رابط منشور قائم، ولا
 * رابط تكرر في الملف نفسه. ورابط الصورة يُحفظ مرجعاً للمحرر ولا يُجلب، فلا اتصال من الخادم بالمنصات.
 *
 * الأعمدة بترتيب القالب بعد صف عناوينه: المنصة، النوع، التاريخ (YYYY-MM-DD)، الرابط، النص، رابط الصورة (اختياري).
 */
class ImportSocialPosts
{
    public const MAX_ROWS = 1000;

    public const MAX_BODY = 5000;

    private const COLUMNS = 6;

    // حجم ملف إكسل بعد فك الضغط كما يعلنه الأرشيف (الرافع محرر موثوق، والحد يرد الخطأ لا المهاجم): ألف منشور أقل من هذا بكثير
    private const MAX_UNCOMPRESSED_BYTES = 20 * 1024 * 1024;

    private const MAX_CSV_BYTES = 5 * 1024 * 1024;

    /**
     * يعيد عدد المسودات المضافة، والصفوف المرفوضة برقمها في الملف وسببها.
     *
     * @return array{created: int, rejected: list<array{row: int, reason: string}>}
     *
     * @throws AuthorizationException
     * @throws ValidationException ملف لا يُقرأ، أو فارغ، أو بغير أعمدة القالب، أو فوق الحد
     */
    public function handle(User $actor, string $path, string $extension): array
    {
        if (! $actor->can('create', SocialPost::class)) {
            throw new AuthorizationException;
        }

        $rows = $this->read($path, strtolower($extension));
        $urls = array_map(fn (array $cells): string => SocialPostUrl::normalize($cells[3]), $rows);
        $existing = array_flip(SocialPost::query()->whereIn('url', array_values(array_unique($urls)))->pluck('url')->all());
        $rejected = [];
        $valid = [];

        foreach ($rows as $number => $cells) {
            [$platformCell, $kindCell, $date, , $body, $image] = $cells;
            $url = $urls[$number];
            $platform = SocialPlatform::fromCell($platformCell);

            $data = [
                'platform' => $platform,
                'kind' => SocialPostKind::fromCell($kindCell),
                'posted_on' => $date,
                'url' => $url,
                'body' => $body,
                'source_image_url' => $image === '' ? null : $image,
            ];

            $validator = Validator::make($data, [
                'platform' => ['required'],
                'kind' => ['required'],
                'posted_on' => ['required', 'date_format:Y-m-d'],
                'url' => ['required', new SocialPostUrl($platform)],
                'body' => ['required', 'string', 'max:'.self::MAX_BODY, new PlainText(multiline: true)],
                'source_image_url' => ['nullable', 'string', 'max:'.SocialPostUrl::MAX_LENGTH, new SafeUrl(allowInternal: false)],
            ], [
                'platform.required' => __('social_posts.import.platform', ['platforms' => self::platforms()]),
                'kind.required' => __('social_posts.import.kind'),
            ], [
                'posted_on' => __('social_posts.fields.posted_on'),
                'url' => __('social_posts.fields.url'),
                'body' => __('social_posts.fields.body'),
                'source_image_url' => __('social_posts.fields.source_image_url'),
            ]);

            if ($validator->fails()) {
                $rejected[$number] = (string) $validator->errors()->first();

                continue;
            }

            if (isset($existing[$url])) {
                $rejected[$number] = __('social_posts.import.duplicate');

                continue;
            }

            $existing[$url] = true;
            $valid[$number] = [...$data, 'is_published' => false];
        }

        $created = 0;

        app(CauserResolver::class)->withCauser($actor, function () use ($valid, &$rejected, &$created): void {
            DB::transaction(function () use ($valid, &$rejected, &$created): void {
                foreach ($valid as $number => $data) {
                    // كل صف في نقطة حفظ: منشور أُضيف برابطه بين الفحص والإدراج (استيراد أو حفظ متزامن) يُرفض صفه مكرراً وحده
                    try {
                        DB::transaction(fn () => SocialPost::query()->create($data));
                        $created++;
                    } catch (UniqueConstraintViolationException) {
                        $rejected[$number] = __('social_posts.import.duplicate');
                    }
                }
            });
        });

        ksort($rejected);

        return [
            'created' => $created,
            'rejected' => array_map(fn (int $row, string $reason): array => ['row' => $row, 'reason' => $reason], array_keys($rejected), array_values($rejected)),
        ];
    }

    /**
     * قالب فارغ بصف العناوين، يُعبأ ثم يُستورد.
     */
    public static function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(self::headings()));
            $writer->close();
        }, 'social-posts-template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * @return list<string>
     */
    public static function headings(): array
    {
        return array_map(fn (string $key): string => (string) __('social_posts.import.columns.'.$key), ['platform', 'kind', 'posted_on', 'url', 'body', 'image']);
    }

    /**
     * أسماء المنصات المقبولة في عمود المنصة، لرسالة الرفض وشرح الاستيراد.
     */
    public static function platforms(): string
    {
        return implode('، ', array_map(fn (SocialPlatform $platform): string => $platform->getLabel(), SocialPlatform::cases()));
    }

    /**
     * صفوف البيانات بعد صف العناوين، برقم الصف في الملف، والخلايا نصوصاً مشذبة (والتاريخ من خلية إكسل YYYY-MM-DD). والصف
     * الفارغ يُتخطى ويبقى رقمه، فيطابق رقم الصف المرفوض رقمه في الملف.
     *
     * @return array<int, list<string>>
     *
     * @throws ValidationException
     */
    private function read(string $path, string $extension): array
    {
        $reader = match ($extension) {
            'xlsx' => $this->xlsxReader($path),
            'csv', 'txt' => $this->csvReader($path),
            default => throw self::fileError('unreadable'),
        };

        try {
            $rows = $this->rows($reader, $path);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw self::fileError('unreadable');
        }

        if ($rows === []) {
            throw self::fileError('empty');
        }

        return $rows;
    }

    /**
     * @throws ValidationException
     */
    private function xlsxReader(string $path): XlsxReader
    {
        // حجم الملف بعد فك ضغطه قبل فتحه: ملايين الصفوف الفارغة المضغوطة في ملف صغير تستنزف المعالج
        $size = self::uncompressedSize($path);

        if ($size === null) {
            throw self::fileError('unreadable');
        }

        if ($size > self::MAX_UNCOMPRESSED_BYTES) {
            throw self::fileError('too_many', ['max' => self::MAX_ROWS]);
        }

        $options = new XlsxOptions;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        return new XlsxReader($options);
    }

    /**
     * CSV بترميز UTF-8، وفاصله الفاصلة أو الفاصلة المنقوطة (إكسل بإعدادات إقليمية) من صف العناوين.
     *
     * @throws ValidationException
     */
    private function csvReader(string $path): CsvReader
    {
        $contents = (string) file_get_contents($path, length: self::MAX_CSV_BYTES + 1);

        if (strlen($contents) > self::MAX_CSV_BYTES) {
            throw self::fileError('too_many', ['max' => self::MAX_ROWS]);
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            throw self::fileError('unreadable');
        }

        $header = strtok($contents, "\n") ?: '';
        $options = new CsvOptions;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $options->FIELD_DELIMITER = substr_count($header, ';') > substr_count($header, ',') ? ';' : ',';

        return new CsvReader($options);
    }

    /**
     * @return array<int, list<string>>
     *
     * @throws ValidationException
     */
    private function rows(XlsxReader|CsvReader $reader, string $path): array
    {
        $rows = [];
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                $cells = array_pad(array_map(self::cell(...), array_slice($row->getCells(), 0, self::COLUMNS)), self::COLUMNS, '');

                // صف العناوين أولاً بأعمدة القالب، فلا يُفقد صف بيانات بصمت ولا تُرفض الصفوف كلها بسبب غير سببها
                if ($index === 1) {
                    if (array_map(fn (string $cell): string => (string) preg_replace('/^\x{FEFF}/u', '', $cell), $cells) !== self::headings()) {
                        throw self::fileError('header', ['columns' => implode('، ', self::headings())]);
                    }

                    continue;
                }

                if (implode('', $cells) === '') {
                    continue;
                }

                $rows[$index] = $cells;

                if (count($rows) > self::MAX_ROWS) {
                    throw self::fileError('too_many', ['max' => self::MAX_ROWS]);
                }
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    /**
     * الخلية نصاً مشذباً: قيمة خلية الصيغة المحسوبة لا نصها، والتاريخ من خلية إكسل بصيغة YYYY-MM-DD.
     */
    private static function cell(Cell $cell): string
    {
        $value = $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell->getValue();

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim(is_scalar($value) ? (string) $value : '');
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    private static function fileError(string $key, array $replace = []): ValidationException
    {
        return ValidationException::withMessages(['file' => __('social_posts.import.'.$key, $replace)]);
    }

    /**
     * مجموع أحجام ملفات الأرشيف بعد فك الضغط كما يعلنها (xlsx أرشيف zip)، وnull إن لم يُقرأ أرشيفاً.
     */
    private static function uncompressedSize(string $path): ?int
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return null;
        }

        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $total += (int) ($zip->statIndex($i)['size'] ?? 0);
        }

        $zip->close();

        return $total;
    }
}
