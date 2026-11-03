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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Activitylog\Support\CauserResolver;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

/**
 * استيراد «منشوراتنا» دفعة واحدة من ملف إكسل أو CSV (طلب صفوان، 3 نوفمبر 2026): كل صف صحيح منشور مسودة، والصف المرفوض
 * يُذكر برقمه وسببه ولا يوقف غيره. والرابط لا يتكرر: لا رابط منشور قائم، ولا رابط تكرر في الملف نفسه. ورابط الصورة يُحفظ
 * مرجعاً للمحرر ولا يُجلب، فلا اتصال من الخادم بالمنصات.
 *
 * الأعمدة بترتيبها بعد صف العناوين: المنصة، النوع، التاريخ (YYYY-MM-DD)، الرابط، النص، رابط الصورة (اختياري).
 */
class ImportSocialPosts
{
    public const MAX_ROWS = 1000;

    public const MAX_BODY = 5000;

    // حجم ملف إكسل بعد فك الضغط: ألف منشور بنصوصها أقل من هذا بكثير
    private const MAX_UNCOMPRESSED_BYTES = 20 * 1024 * 1024;

    /**
     * يعيد عدد المسودات المضافة، والصفوف المرفوضة برقمها في الملف وسببها.
     *
     * @return array{created: int, rejected: list<array{row: int, reason: string}>}
     *
     * @throws AuthorizationException
     * @throws ValidationException ملف لا يُقرأ، أو فارغ، أو فوق الحد
     */
    public function handle(User $actor, string $path, string $extension): array
    {
        if (! $actor->can('create', SocialPost::class)) {
            throw new AuthorizationException;
        }

        $rows = $this->read($path, strtolower($extension));
        $rejected = [];
        $valid = [];
        $seen = [];

        foreach ($rows as $number => $cells) {
            [$platformCell, $kindCell, $date, $url, $body, $image] = $cells;
            $platform = SocialPlatform::fromCell($platformCell);
            $url = SocialPostUrl::normalize($url);

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
                'platform.required' => __('social_posts.import.platform'),
                'kind.required' => __('social_posts.import.kind'),
            ], [
                'posted_on' => __('social_posts.fields.posted_on'),
                'url' => __('social_posts.fields.url'),
                'body' => __('social_posts.fields.body'),
                'source_image_url' => __('social_posts.fields.source_image_url'),
            ]);

            if ($validator->fails()) {
                $rejected[] = ['row' => $number, 'reason' => (string) $validator->errors()->first()];

                continue;
            }

            if (isset($seen[$url]) || SocialPost::query()->where('url', $url)->exists()) {
                $rejected[] = ['row' => $number, 'reason' => __('social_posts.import.duplicate')];

                continue;
            }

            $seen[$url] = true;
            $valid[] = [...$data, 'is_published' => false];
        }

        app(CauserResolver::class)->withCauser($actor, function () use ($valid): void {
            DB::transaction(function () use ($valid): void {
                foreach ($valid as $data) {
                    SocialPost::query()->create($data);
                }
            });
        });

        return ['created' => count($valid), 'rejected' => $rejected];
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
     * صفوف البيانات بعد صف العناوين، برقم الصف في الملف، والخلايا نصوصاً مشذبة (والتاريخ من خلية إكسل YYYY-MM-DD). والصف
     * الفارغ يُتخطى.
     *
     * @return array<int, list<string>>
     *
     * @throws ValidationException
     */
    private function read(string $path, string $extension): array
    {
        $reader = match ($extension) {
            'xlsx' => new XlsxReader,
            'csv', 'txt' => new CsvReader,
            default => throw ValidationException::withMessages(['file' => __('social_posts.import.unreadable')]),
        };

        // حجم الملف بعد فك ضغطه قبل فتحه: ملايين الصفوف الفارغة المضغوطة في ملف صغير تستنزف المعالج
        $size = $reader instanceof XlsxReader ? self::uncompressedSize($path) : 0;

        if ($size === null) {
            throw ValidationException::withMessages(['file' => __('social_posts.import.unreadable')]);
        }

        if ($size > self::MAX_UNCOMPRESSED_BYTES) {
            throw ValidationException::withMessages(['file' => __('social_posts.import.too_many', ['max' => self::MAX_ROWS])]);
        }

        $rows = [];

        try {
            $rows = $this->rows($reader, $path);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => __('social_posts.import.unreadable')]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => __('social_posts.import.empty')]);
        }

        return $rows;
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
                if ($index === 1) {
                    continue;
                }

                $cells = array_map(self::cell(...), array_slice($row->toArray(), 0, 6));

                if (implode('', $cells) === '') {
                    continue;
                }

                $rows[$index] = array_pad($cells, 6, '');

                if (count($rows) > self::MAX_ROWS) {
                    throw ValidationException::withMessages(['file' => __('social_posts.import.too_many', ['max' => self::MAX_ROWS])]);
                }
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    /**
     * الخلية نصاً مشذباً، والتاريخ من خلية إكسل بصيغة YYYY-MM-DD. والخلية التي حماها التصدير من الصيغ بفاصلة عليا تعود كما
     * كانت (كاستيراد بنك الأسئلة — ImportStudentQuestions).
     */
    private static function cell(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) preg_replace("/^'(?=[\\s\\x{00A0}\\x{FEFF}]*[=+\\-@\\t\\r])/u", '', trim(is_scalar($value) ? (string) $value : ''));
    }

    /**
     * مجموع أحجام ملفات الأرشيف بعد فك الضغط (xlsx أرشيف zip)، وnull إن لم يُقرأ أرشيفاً.
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
