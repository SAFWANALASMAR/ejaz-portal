<?php

namespace App\Actions\Content;

use App\Actions\Governance\SaveDocument;
use App\Actions\Library\SaveLibraryItem;
use App\Enums\DocumentCategory;
use App\Enums\LibrarySection;
use App\Enums\RedirectType;
use App\Enums\SiteIcon;
use App\Models\Document;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\User;
use App\Rules\PlainText;
use App\Rules\VideoUrl;
use App\Support\RedirectMap;
use App\Support\RichText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\File;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Support\CauserResolver;

/**
 * استيراد محتوى الموقع الحالي من ملف JSON يُعد بعد اعتماد الجمعية لنصوصه (content-inventory.md):
 * الصفحات، والمبادرات، والوثائق، وعناصر المكتبة، وتحويلات إضافية. كل سجل يُتحقق منه بقواعد
 * شاشته، ثم يُحفظ بإجراءاتها نفسها باسم المنفّذ، مسودةً غير منشورة.
 *
 * والسجل الموجود بمعرّفه: المسودة تُملأ حقولها الفارغة وحدها، والمنشور والمحذوف لا يُمسّان. فإعادة
 * التشغيل لا تكرر شيئاً، ولا تمحو ما كتبه المحرر ولا ملفاته. ولكل سجل روابطه القديمة تُحوَّل إلى صفحته.
 */
class ImportLegacyContent
{
    /**
     * أنواع السجلات بترتيب الاستيراد، ونماذجها.
     */
    private const MODELS = [
        'pages' => Page::class,
        'initiatives' => Initiative::class,
        'documents' => Document::class,
        'library' => LibraryItem::class,
    ];

    private const MAX_FILE_BYTES = 20 * 1024 * 1024;

    private const MAX_AUDIO_BYTES = 25 * 1024 * 1024;

    /** @var list<array{section: string, key: string, outcome: string, detail: string}> */
    private array $report = [];

    /** @var list<array{0: string, 1: string}> القرص والمسار لكل ملف نُسخ، لتنظيف التجربة */
    private array $copied = [];

    public function __construct(
        private SaveContent $saveContent,
        private SaveDocument $saveDocument,
        private SaveLibraryItem $saveLibraryItem,
        private SaveRedirect $saveRedirect,
    ) {}

    /**
     * @return list<array{section: string, key: string, outcome: string, detail: string}>
     *
     * @throws ValidationException ملف لا يُقرأ أو ببنية غير صحيحة
     * @throws AuthorizationException المنفّذ لا يملك الإنشاء في نوع موجود في الملف
     */
    public function handle(User $actor, string $path, bool $dryRun = false): array
    {
        $data = self::read($path);
        $base = dirname((string) realpath($path));

        self::authorize($actor, $data);

        $this->report = [];
        $this->copied = [];

        app(CauserResolver::class)->withCauser($actor, function () use ($actor, $data, $base, $dryRun): void {
            if (! $dryRun) {
                $this->import($actor, $data, $base);

                return;
            }

            // التجربة كلها في معاملة تُلغى، والملفات المنسوخة تُحذف، فلا يبقى منها شيء
            DB::beginTransaction();

            try {
                $this->import($actor, $data, $base);
            } finally {
                DB::rollBack();

                foreach ($this->copied as [$disk, $file]) {
                    Storage::disk($disk)->delete($file);
                }
            }
        });

        return $this->report;
    }

    /**
     * @param  array<string, list<mixed>>  $data
     */
    private function import(User $actor, array $data, string $base): void
    {
        foreach (array_keys(self::MODELS) as $section) {
            foreach ($data[$section] ?? [] as $index => $record) {
                $this->importRecord($actor, $section, $index, $record, $base);
            }
        }

        foreach ($data['redirects'] ?? [] as $index => $redirect) {
            $validator = Validator::make(is_array($redirect) ? $redirect : [], [
                'from' => ['required', 'string', 'max:1000'],
                'to' => ['required', 'string', 'max:500'],
            ]);

            if ($validator->fails()) {
                $this->add('redirects', '#'.($index + 1), 'rejected', ['reason' => $validator->errors()->first()]);

                continue;
            }

            $this->redirect($actor, 'redirects', '#'.($index + 1), (string) $redirect['from'], (string) $redirect['to']);
        }
    }

    private function importRecord(User $actor, string $section, int $index, mixed $record, string $base): void
    {
        $record = is_array($record) ? $record : [];
        $key = is_string($record['slug'] ?? null) && $record['slug'] !== '' ? Str::limit($record['slug'], 80) : '#'.($index + 1);
        $validator = Validator::make($record, self::rules($section));

        if ($validator->fails()) {
            $this->add($section, $key, 'rejected', ['reason' => $validator->errors()->first()]);

            return;
        }

        $values = $validator->validated();
        $class = self::MODELS[$section];
        $query = $class::query();

        if ($class === Document::class || $class === LibraryItem::class) {
            $query->withTrashed();
        }

        /** @var Page|Initiative|Document|LibraryItem|null $existing */
        $existing = $query->where('slug', $values['slug'])->first();

        if (($existing instanceof Document || $existing instanceof LibraryItem) && $existing->trashed()) {
            $this->add($section, $key, 'deleted');

            return;
        }

        if ($existing?->is_published) {
            $this->add($section, $key, 'published');
            $this->redirectLegacyPaths($actor, $section, $key, $values, $existing);

            return;
        }

        $fields = Arr::except($values, ['slug', 'file', 'legacy_paths']);

        // المسودة الموجودة: الفارغ وحده، فلا يُمحى ما كتبه المحرر، ولا تُستبدل صوره ولا ملفاته
        $fields = $existing === null
            ? [...$fields, 'slug' => $values['slug'], 'is_published' => false]
            : array_filter($fields, fn (mixed $value, string $field): bool => filled($value) && blank($existing->getAttribute($field)), ARRAY_FILTER_USE_BOTH);

        try {
            if (filled($values['file'] ?? null) && blank($existing?->getAttribute('file'))) {
                $fields['file'] = $this->copyFile($section, $values, $base);
            }

            if ($existing !== null && $fields === []) {
                $this->add($section, $key, 'unchanged');
                $this->redirectLegacyPaths($actor, $section, $key, $values, $existing);

                return;
            }

            $saved = match ($section) {
                'documents' => $this->saveDocument->handle($actor, $existing instanceof Document ? $existing : new Document, $fields),
                'library' => $this->saveLibraryItem->handle($actor, $existing instanceof LibraryItem ? $existing : new LibraryItem, $fields),
                default => $this->saveContent->handle($actor, $existing ?? new $class, $fields),
            };
        } catch (ValidationException $exception) {
            // الملف المنسوخ لسجل رُفض لا يبقى في القرص (والإجراء يحذف ما يرفضه بفحصه)
            if (isset($fields['file']) && is_string($fields['file'])) {
                Storage::disk($class === Document::class ? Document::disk() : LibraryItem::disk())->delete($fields['file']);
            }

            $this->add($section, $key, 'rejected', ['reason' => collect($exception->errors())->flatten()->first()]);

            return;
        }

        $existing === null
            ? $this->add($section, $key, 'created')
            : $this->add($section, $key, 'filled', ['fields' => implode('، ', array_keys($fields))]);

        $this->redirectLegacyPaths($actor, $section, $key, $values, $saved);
    }

    /**
     * ينسخ الملف من مجلد ملف الاستيراد وحده إلى القرص الخاص باسم عشوائي، والإجراء يفحصه بعد ذلك.
     *
     * @param  array<string, mixed>  $values
     *
     * @throws ValidationException
     */
    private function copyFile(string $section, array $values, string $base): string
    {
        $relative = (string) $values['file'];
        $full = realpath($base.DIRECTORY_SEPARATOR.$relative);

        if ($full === false || ! is_file($full) || ! str_starts_with($full, $base.DIRECTORY_SEPARATOR)) {
            throw ValidationException::withMessages(['file' => __('import.errors.file_missing', ['file' => $relative])]);
        }

        $librarySection = $section === 'library' ? LibrarySection::from((string) $values['section']) : null;

        if ($librarySection?->takesVideoUrl()) {
            throw ValidationException::withMessages(['file' => __('import.errors.video_file')]);
        }

        // المستند والكتاب PDF، والصوت بامتداده ليفحص الإجراء نوعه
        $isAudio = $librarySection !== null && ! $librarySection->takesPdf();

        if (filesize($full) > ($isAudio ? self::MAX_AUDIO_BYTES : self::MAX_FILE_BYTES)) {
            throw ValidationException::withMessages(['file' => __('import.errors.file_size', ['file' => $relative])]);
        }

        $extension = $isAudio ? strtolower(pathinfo($full, PATHINFO_EXTENSION)) : 'pdf';
        $disk = $section === 'documents' ? Document::disk() : LibraryItem::disk();
        $directory = $section === 'documents' ? Document::DIRECTORY : LibraryItem::FILE_DIRECTORY;

        $stored = (string) Storage::disk($disk)->putFileAs($directory, new File($full), Str::random(40).'.'.$extension);
        $this->copied[] = [$disk, $stored];

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function redirectLegacyPaths(User $actor, string $section, string $key, array $values, Model $record): void
    {
        foreach ($values['legacy_paths'] ?? [] as $path) {
            $this->redirect($actor, $section, $key, (string) $path, self::url($record));
        }
    }

    private function redirect(User $actor, string $section, string $key, string $from, string $to): void
    {
        if (Redirect::query()->where('source', RedirectMap::normalize($from))->exists()) {
            $this->add($section, $key, 'redirect_exists', ['from' => $from]);

            return;
        }

        try {
            $this->saveRedirect->handle($actor, new Redirect, ['source' => $from, 'target' => $to, 'type' => RedirectType::Permanent->value]);
        } catch (ValidationException $exception) {
            $this->add($section, $key, 'rejected', ['reason' => $from.': '.collect($exception->errors())->flatten()->first()]);

            return;
        }

        $this->add($section, $key, 'redirect_created', ['from' => $from, 'to' => $to]);
    }

    /**
     * مسار صفحة السجل في البوابة، وجهةً لروابطه القديمة.
     */
    private static function url(Model $record): string
    {
        return match (true) {
            $record instanceof Page && $record->slug === Page::ABOUT => route('about', absolute: false),
            $record instanceof Page => route('pages.show', $record->slug, false),
            $record instanceof Initiative => route('initiatives.show', $record->slug, false),
            $record instanceof Document => route('governance.show', $record->slug, false),
            $record instanceof LibraryItem => route('library.show', [$record->section->value, $record->slug], false),
            default => throw new \LogicException('No public page for '.$record::class),
        };
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function add(string $section, string $key, string $outcome, array $replace = []): void
    {
        $this->report[] = ['section' => $section, 'key' => $key, 'outcome' => $outcome, 'detail' => __('import.outcomes.'.$outcome, $replace)];
    }

    /**
     * @return array<string, list<mixed>>
     *
     * @throws ValidationException
     */
    private static function read(string $path): array
    {
        $contents = is_file($path) && filesize($path) <= self::MAX_FILE_BYTES ? file_get_contents($path) : false;

        try {
            $data = $contents === false ? null : json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $data = null;
        }

        if (! is_array($data)) {
            throw ValidationException::withMessages(['file' => __('import.errors.file')]);
        }

        $sections = [...array_keys(self::MODELS), 'redirects'];
        $validator = Validator::make($data, array_fill_keys($sections, ['sometimes', 'array', 'list']));
        $unknown = array_diff(array_keys($data), $sections);

        if ($validator->fails() || $unknown !== []) {
            $reason = $unknown !== [] ? implode('، ', array_map(strval(...), $unknown)) : (string) $validator->errors()->first();

            throw ValidationException::withMessages(['file' => __('import.errors.structure', ['reason' => $reason])]);
        }

        /** @var array<string, list<mixed>> $data */
        return $data;
    }

    /**
     * يُفحص الإنشاء في كل نوع موجود في الملف قبل أي حفظ، فلا يتوقف الاستيراد في منتصفه.
     *
     * @param  array<string, list<mixed>>  $data
     *
     * @throws AuthorizationException
     */
    private static function authorize(User $actor, array $data): void
    {
        $missing = [];

        foreach (self::MODELS as $section => $class) {
            if (($data[$section] ?? []) !== [] && ! $actor->can('create', $class)) {
                $missing[] = __('import.sections.'.$section);
            }
        }

        $hasRedirects = ($data['redirects'] ?? []) !== [] || collect(array_keys(self::MODELS))
            ->contains(fn (string $section): bool => collect($data[$section] ?? [])->contains(fn (mixed $record): bool => is_array($record) && filled($record['legacy_paths'] ?? null)));

        if ($hasRedirects && ! $actor->can('create', Redirect::class)) {
            $missing[] = __('import.sections.redirects');
        }

        if ($missing !== []) {
            throw new AuthorizationException(__('import.errors.forbidden', ['sections' => implode('، ', $missing)]));
        }
    }

    /**
     * قواعد كل نوع، بحدود شاشته في اللوحة. والمقاطع نص وبطاقات، والصور تُرفع من اللوحة لتُنظَّف.
     *
     * @return array<string, mixed>
     */
    private static function rules(string $section): array
    {
        $slug = ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'];
        $legacy = ['legacy_paths' => ['sometimes', 'array', 'list', 'max:20'], 'legacy_paths.*' => ['required', 'string', 'max:1000']];
        $blocks = [
            'blocks' => ['sometimes', 'array', 'list', 'max:30'],
            'blocks.*.type' => ['required', Rule::in(['text', 'cards'])],
            'blocks.*.data' => ['required', 'array'],
            'blocks.*.data.heading' => ['nullable', 'string', 'max:160', new PlainText],
            'blocks.*.data.body' => ['exclude_unless:blocks.*.type,text', 'required', 'string', 'max:'.RichText::MAX_LENGTH],
            'blocks.*.data.items' => ['exclude_unless:blocks.*.type,cards', 'required', 'array', 'list', 'min:1', 'max:12'],
            'blocks.*.data.items.*.icon' => ['nullable', Rule::enum(SiteIcon::class)],
            'blocks.*.data.items.*.title' => ['required', 'string', 'max:120', new PlainText],
            'blocks.*.data.items.*.text' => ['nullable', 'string', 'max:600', new PlainText(multiline: true)],
        ];
        $summary = ['nullable', 'string', 'max:300', new PlainText(multiline: true)];

        return match ($section) {
            'pages' => [
                'slug' => $slug,
                'title' => ['required', 'string', 'max:160', new PlainText],
                'summary' => $summary,
                ...$blocks,
                ...$legacy,
            ],
            'initiatives' => [
                'slug' => $slug,
                'name' => ['required', 'string', 'max:120', new PlainText],
                'summary' => $summary,
                'goals' => ['sometimes', 'array', 'list', 'max:12'],
                'goals.*' => ['required', 'string', 'max:300', new PlainText],
                ...$blocks,
                ...$legacy,
            ],
            'documents' => [
                'slug' => $slug,
                'title' => ['required', 'string', 'max:200', new PlainText],
                'category' => ['required', Rule::enum(DocumentCategory::class)],
                'issued_at' => ['nullable', 'date_format:Y-m-d'],
                'version' => ['nullable', 'string', 'max:40', new PlainText],
                'description' => ['nullable', 'string', 'max:1000', new PlainText(multiline: true)],
                'file' => ['required', 'string', 'max:500'],
                ...$legacy,
            ],
            'library' => [
                'slug' => $slug,
                'section' => ['required', Rule::enum(LibrarySection::class)],
                'title' => ['required', 'string', 'max:200', new PlainText],
                'author' => ['nullable', 'string', 'max:150', new PlainText],
                'published_on' => ['nullable', 'date_format:Y-m-d'],
                'tags' => ['sometimes', 'array', 'list', 'max:'.LibraryItem::MAX_TAGS],
                'tags.*' => ['required', 'string', 'max:'.LibraryItem::MAX_TAG_LENGTH, new PlainText],
                'description' => ['nullable', 'string', 'max:2000', new PlainText(multiline: true)],
                'video_url' => ['nullable', 'string', 'max:'.VideoUrl::MAX_LENGTH],
                'file' => ['nullable', 'string', 'max:500'],
                ...$legacy,
            ],
            default => throw new \LogicException('Unknown section '.$section),
        };
    }
}
