<?php

namespace App\Actions\Content;

use App\Actions\Governance\SaveDocument;
use App\Actions\Library\SaveLibraryItem;
use App\Actions\Settings\UpdateSiteSettings;
use App\Enums\DocumentCategory;
use App\Enums\LibrarySection;
use App\Enums\RedirectType;
use App\Enums\SiteIcon;
use App\Filament\Pages\SiteSettings;
use App\Models\Document;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\User;
use App\Rules\PlainText;
use App\Rules\SafeUrl;
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
 * مكتبة الوسائط، والصفحات، والمبادرات، والوثائق، وعناصر المكتبة، وإعدادات الرئيسية والتواصل، وتحويلات
 * إضافية. كل سجل يُتحقق منه بقواعد شاشته، ثم يُحفظ بإجراءاتها نفسها باسم المنفّذ، مسودةً غير منشورة.
 * وصورة المقطع في الملف معرّف عنصر في مكتبة الوسائط (media)، تُنسخ نسخته للويب إلى مجلد السجل.
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

    /**
     * مجموعات الإعدادات التي يملأ الاستيراد الفارغ منها (importSettings).
     */
    private const SETTINGS = ['home', 'contact'];

    /** @var list<array{section: string, key: string, outcome: string, detail: string}> */
    private array $report = [];

    /** @var list<array{0: string, 1: string}> القرص والمسار لكل ملف نُسخ، لتنظيف التجربة */
    private array $copied = [];

    /** @var list<string> نسخ الصور التي وضعها السجل الجاري في القرص العام، فتُحذف وحدها إن رُفض */
    private array $placed = [];

    public function __construct(
        private SaveContent $saveContent,
        private SaveDocument $saveDocument,
        private SaveLibraryItem $saveLibraryItem,
        private SaveRedirect $saveRedirect,
        private AddMediaAsset $addMediaAsset,
        private UpdateSiteSettings $updateSettings,
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
        $base = rtrim(dirname((string) realpath($path)), '\/');

        // ملف في جذر القرص يجعل فحص «داخل المجلد» يقبل أي مسار مطلق
        if ($base === '' || preg_match('/^[A-Za-z]:$/', $base) === 1) {
            throw ValidationException::withMessages(['file' => __('import.errors.file')]);
        }

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
     * @param  array<string, array<mixed>>  $data
     */
    private function import(User $actor, array $data, string $base): void
    {
        // الوسائط أولاً، لأن مقاطع الصفحات وصورة الرئيسية تشير إليها
        foreach ($data['media'] ?? [] as $index => $record) {
            $this->importMedia($actor, $index, $record, $base);
        }

        foreach (array_keys(self::MODELS) as $section) {
            foreach ($data[$section] ?? [] as $index => $record) {
                $this->importRecord($actor, $section, $index, $record, $base);
            }
        }

        foreach (self::SETTINGS as $group) {
            if (is_array($data['settings'][$group] ?? null)) {
                $this->importSettings($actor, $group, $data['settings'][$group]);
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
        $key = is_string($record['slug'] ?? null) && preg_match('/^[a-z0-9-]{1,80}$/', $record['slug']) === 1 ? $record['slug'] : '#'.($index + 1);
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

            $this->placed = [];

            if (isset($fields['blocks'])) {
                $fields['blocks'] = $this->placeMedia((array) $fields['blocks'], (new $class)->imageDirectory());
            }

            if ($existing !== null && $fields === []) {
                $this->add($section, $key, 'unchanged');
                $this->redirectLegacyPaths($actor, $section, $key, $values, $existing);

                return;
            }

            $saved = match ($section) {
                'documents' => $this->saveDocument->handle($actor, $existing instanceof Document ? $existing : new Document, $fields),
                'library' => $this->saveLibraryItem->handle($actor, $existing instanceof LibraryItem ? $existing : new LibraryItem, $fields, legacyFile: true),
                default => $this->saveContent->handle($actor, $existing ?? new $class, $fields),
            };
        } catch (ValidationException $exception) {
            // الملف المنسوخ لسجل رُفض لا يبقى في القرص (والإجراء يحذف ما يرفضه بفحصه)
            if (isset($fields['file']) && is_string($fields['file'])) {
                Storage::disk($class === Document::class ? Document::disk() : LibraryItem::disk())->delete($fields['file']);
            }

            // ونسخ الصور التي وضعها هذا السجل وحدها، لا أي مسار كُتب في الملف
            Storage::disk('public')->delete($this->placed);

            $this->add($section, $key, 'rejected', ['reason' => collect($exception->errors())->flatten()->first()]);

            return;
        }

        $existing === null
            ? $this->add($section, $key, 'created')
            : $this->add($section, $key, 'filled', ['fields' => implode('، ', array_keys($fields))]);

        $this->redirectLegacyPaths($actor, $section, $key, $values, $saved);
    }

    /**
     * عنصر مكتبة الوسائط: ملفه من مجلد ملف الاستيراد وحده، ويُحفظ بإجراء الإضافة. والموجود بمعرّفه
     * لا يُمس، ولروابطه القديمة تحويل إلى مكانه الجديد (target).
     */
    private function importMedia(User $actor, int $index, mixed $record, string $base): void
    {
        $record = is_array($record) ? $record : [];
        $key = is_string($record['slug'] ?? null) && preg_match('/^[a-z0-9-]{1,80}$/', $record['slug']) === 1 ? $record['slug'] : '#'.($index + 1);
        $validator = Validator::make($record, [
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'file' => ['required', 'string', 'max:500'],
            'title' => ['required', 'string'],
            'alt' => ['nullable', 'string'],
            'is_decorative' => ['sometimes', 'boolean'],
            'from_template' => ['sometimes', 'boolean'],
            'target' => ['nullable', 'string', 'max:500'],
            'legacy_paths' => ['sometimes', 'array', 'list', 'max:20'],
            'legacy_paths.*' => ['required', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            $this->add('media', $key, 'rejected', ['reason' => $validator->errors()->first()]);

            return;
        }

        $values = $validator->validated();
        $asset = MediaAsset::query()->where('slug', $values['slug'])->first();

        if ($asset !== null) {
            $this->add('media', $key, 'unchanged');
        } else {
            $relative = (string) $values['file'];
            $full = realpath($base.DIRECTORY_SEPARATOR.$relative);

            if ($full === false || ! is_file($full) || ! str_starts_with($full, $base.DIRECTORY_SEPARATOR)) {
                $this->add('media', $key, 'rejected', ['reason' => __('import.errors.file_missing', ['file' => $relative])]);

                return;
            }

            try {
                $asset = $this->addMediaAsset->handle($actor, $full, [
                    ...Arr::only($values, ['slug', 'title', 'alt', 'is_decorative', 'from_template']),
                    'legacy_url' => $values['legacy_paths'][0] ?? null,
                ]);
            } catch (ValidationException $exception) {
                $this->add('media', $key, 'rejected', ['reason' => collect($exception->errors())->flatten()->first()]);

                return;
            }

            $this->copied[] = [MediaAsset::disk(), $asset->original_path];

            if ($asset->web_path !== null) {
                $this->copied[] = ['public', $asset->web_path];
            }

            $this->add('media', $key, 'created');
        }

        foreach (filled($values['target'] ?? null) ? $values['legacy_paths'] ?? [] : [] as $path) {
            $this->redirect($actor, 'media', $key, (string) $path, (string) $values['target']);
        }
    }

    /**
     * مقاطع الصورة وصور البطاقات بمعرّف عنصر من مكتبة الوسائط: تُنسخ نسخته للويب إلى مجلد السجل،
     * ونصها البديل نصه البديل (فارغ للزخرفية). فلا يمس تعديلُ الصفحة ملفَّ المكتبة.
     *
     * @param  array<int, mixed>  $blocks
     * @return list<mixed>
     *
     * @throws ValidationException
     */
    private function placeMedia(array $blocks, string $directory): array
    {
        $place = function (string $slug) use ($directory): array {
            $asset = MediaAsset::query()->where('slug', $slug)->first();
            $path = $asset === null ? null : AddMediaAsset::placeIn($asset, $directory);

            if ($asset === null || $path === null) {
                throw ValidationException::withMessages(['blocks' => __('import.errors.media_missing', ['media' => $slug])]);
            }

            $this->copied[] = ['public', $path];
            $this->placed[] = $path;

            return [$path, $asset->altText()];
        };

        foreach ($blocks as $i => $block) {
            $type = is_array($block) ? ($block['type'] ?? null) : null;
            $data = is_array($block) ? (array) ($block['data'] ?? []) : [];

            if ($type === 'image') {
                [$picture, $alt] = $place((string) $data['media']);
                $blocks[$i]['data'] = ['picture' => $picture, 'alt' => $alt, 'caption' => $data['caption'] ?? null];
            }

            if ($type === 'cards') {
                foreach ((array) ($data['items'] ?? []) as $j => $item) {
                    $media = is_array($item) ? ($item['media'] ?? null) : null;
                    unset($data['items'][$j]['media']);

                    if (is_string($media) && $media !== '') {
                        $data['items'][$j]['picture'] = $place($media)[0];
                    }
                }

                $blocks[$i]['data'] = $data;
            }
        }

        return array_values($blocks);
    }

    /**
     * إعدادات الرئيسية أو التواصل: يُملأ الفارغ منها وحده بإجراء حفظ الإعدادات، فلا يُمحى ما أدخله
     * المحرر. وما في الملف يُفحص بقواعد شاشة الإعدادات، وhero_media معرّف عنصر في مكتبة الوسائط
     * يصير صورة الرئيسية ونصها البديل.
     *
     * @param  array<mixed>  $values
     */
    private function importSettings(User $actor, string $group, array $values): void
    {
        $rules = $group === 'home' ? [
            'hero_title' => ['nullable', 'string', 'max:120', new PlainText],
            'hero_text' => ['nullable', 'string', 'max:400', new PlainText(multiline: true)],
            'hero_button_label' => ['nullable', 'string', 'max:40', new PlainText],
            'hero_button_url' => ['nullable', 'string', 'max:500', new SafeUrl],
            'hero_media' => ['nullable', 'string', 'max:80'],
            'video_url' => ['nullable', 'string', 'max:'.VideoUrl::MAX_LENGTH, new VideoUrl(youtubeOnly: true)],
        ] : [
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ]+$/'],
            'address' => ['nullable', 'string', 'max:300', new PlainText(multiline: true)],
            'map_url' => ['nullable', 'string', 'max:1000', new SafeUrl(allowInternal: false)],
            'social' => ['nullable', 'array:'.implode(',', SiteSettings::SOCIAL)],
            'social.*' => ['nullable', 'string', 'max:500', new SafeUrl(allowInternal: false)],
        ];

        $validator = Validator::make($values, $rules);
        $unknown = array_diff(array_keys($values), array_keys($rules));

        if ($validator->fails() || $unknown !== []) {
            $this->add('settings', $group, 'rejected', ['reason' => $unknown !== [] ? implode('، ', array_map(strval(...), $unknown)) : $validator->errors()->first()]);

            return;
        }

        $current = Setting::group($group);
        $placed = null;
        $fill = array_filter($validator->validated(), fn (mixed $value, string $field): bool => filled($value)
            && blank($current[$field === 'hero_media' ? 'hero_image' : $field] ?? null), ARRAY_FILTER_USE_BOTH);

        // حسابات التواصل حساباً حساباً: يُضاف ما لا رابط له، ولا يُمس ما أدخله المحرر
        $social = array_diff_key(array_filter((array) ($validator->validated()['social'] ?? [])), array_filter((array) ($current['social'] ?? [])));
        unset($fill['social']);
        if ($social !== []) {
            $fill['social'] = [...(array) ($current['social'] ?? []), ...$social];
        }

        try {
            if (isset($fill['hero_media'])) {
                $asset = MediaAsset::query()->where('slug', $fill['hero_media'])->first();
                $path = $asset === null ? null : AddMediaAsset::placeIn($asset, UpdateSiteSettings::IMAGE_DIRECTORY);

                if ($asset === null || $path === null) {
                    throw ValidationException::withMessages(['hero_media' => __('import.errors.media_missing', ['media' => $fill['hero_media']])]);
                }

                $this->copied[] = ['public', $path];
                $placed = $path;
                unset($fill['hero_media']);
                $fill['hero_image'] = $path;

                if (blank($current['hero_image_alt'] ?? null)) {
                    $fill['hero_image_alt'] = $asset->altText();
                }
            }

            if ($fill === []) {
                $this->add('settings', $group, 'unchanged');

                return;
            }

            $this->updateSettings->handle($actor, [$group => [...$current, ...$fill]]);
        } catch (ValidationException $exception) {
            if ($placed !== null) {
                Storage::disk('public')->delete($placed);
            }

            $this->add('settings', $group, 'rejected', ['reason' => collect($exception->errors())->flatten()->first()]);

            return;
        }

        $this->add('settings', $group, 'filled', ['fields' => implode('، ', array_keys($fill))]);
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

        // والصوتية من الموقع السابق ملفات تبقى تعمل (LibrarySection::keepsLegacyFile)
        if ($librarySection?->takesVideoUrl() && ! $librarySection->keepsLegacyFile()) {
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
            $record instanceof Page && $record->slug === Page::PRIVACY => route('privacy', absolute: false),
            $record instanceof Page && isset(Page::INTROS[$record->slug]) => (string) Page::introUrl($record->slug, false),
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
        // ما يُطبع في الطرفية من الملف بلا محارف تحكم ولا قلب اتجاه، فلا يعبث بها نص مُعد
        $replace = array_map(fn (string $value): string => (string) preg_replace('/[\p{Cc}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $value), $replace);

        $this->report[] = ['section' => $section, 'key' => $key, 'outcome' => $outcome, 'detail' => __('import.outcomes.'.$outcome, $replace)];
    }

    /**
     * @return array<string, array<mixed>>
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

        $sections = ['media', ...array_keys(self::MODELS), 'settings', 'redirects'];
        $validator = Validator::make($data, [...array_fill_keys($sections, ['sometimes', 'array', 'list']), 'settings' => ['sometimes', 'array']]);
        $unknown = array_diff(array_keys($data), $sections);

        if ($validator->fails() || $unknown !== []) {
            $reason = $unknown !== [] ? implode('، ', array_map(strval(...), $unknown)) : (string) $validator->errors()->first();

            throw ValidationException::withMessages(['file' => __('import.errors.structure', ['reason' => $reason])]);
        }

        /** @var array<string, array<mixed>> $data */
        return $data;
    }

    /**
     * يُفحص الإنشاء والتعديل في كل نوع موجود في الملف قبل أي حفظ، فلا يتوقف الاستيراد في منتصفه.
     *
     * @param  array<string, array<mixed>>  $data
     *
     * @throws AuthorizationException
     */
    private static function authorize(User $actor, array $data): void
    {
        $missing = [];

        // والتعديل أيضاً، لأن المسودة الموجودة تُملأ
        foreach (self::MODELS as $section => $class) {
            if (($data[$section] ?? []) !== [] && (! $actor->can('create', $class) || ! $actor->can('update', new $class))) {
                $missing[] = __('import.sections.'.$section);
            }
        }

        $hasRedirects = ($data['redirects'] ?? []) !== [] || collect(array_keys(self::MODELS))
            ->contains(fn (string $section): bool => collect($data[$section] ?? [])->contains(fn (mixed $record): bool => is_array($record) && filled($record['legacy_paths'] ?? null)));

        if ($hasRedirects && ! $actor->can('create', Redirect::class)) {
            $missing[] = __('import.sections.redirects');
        }

        if (($data['media'] ?? []) !== [] && ! $actor->can('create', MediaAsset::class)) {
            $missing[] = __('import.sections.media');
        }

        if (($data['settings'] ?? []) !== [] && ! $actor->can('update', Setting::class)) {
            $missing[] = __('import.sections.settings');
        }

        if ($missing !== []) {
            throw new AuthorizationException(__('import.errors.forbidden', ['sections' => implode('، ', $missing)]));
        }
    }

    /**
     * قواعد كل نوع، بحدود شاشته في اللوحة. والمقاطع نص وبطاقات وصورة، والصورة معرّف عنصر في مكتبة
     * الوسائط تُنظَّف نسخته عند الحفظ كأي صورة مرفوعة.
     *
     * @return array<string, mixed>
     */
    private static function rules(string $section): array
    {
        $slug = ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'];
        $legacy = ['legacy_paths' => ['sometimes', 'array', 'list', 'max:20'], 'legacy_paths.*' => ['required', 'string', 'max:1000']];
        $blocks = [
            'blocks' => ['sometimes', 'array', 'list', 'max:30'],
            'blocks.*.type' => ['required', Rule::in(['text', 'cards', 'image'])],
            'blocks.*.data' => ['required', 'array'],
            'blocks.*.data.heading' => ['nullable', 'string', 'max:160', new PlainText],
            'blocks.*.data.body' => ['exclude_unless:blocks.*.type,text', 'required', 'string', 'max:'.RichText::MAX_LENGTH],
            'blocks.*.data.items' => ['exclude_unless:blocks.*.type,cards', 'required', 'array', 'list', 'min:1', 'max:12'],
            'blocks.*.data.items.*.icon' => ['nullable', Rule::enum(SiteIcon::class)],
            'blocks.*.data.items.*.title' => ['required', 'string', 'max:120', new PlainText],
            'blocks.*.data.items.*.text' => ['nullable', 'string', 'max:600', new PlainText(multiline: true)],
            'blocks.*.data.items.*.media' => ['nullable', 'string', 'max:80'],
            // الصورة من مكتبة الوسائط وحدها، لا مسار يُكتب في الملف
            'blocks.*.data.items.*.picture' => ['prohibited'],
            'blocks.*.data.picture' => ['prohibited'],
            'blocks.*.data.media' => ['exclude_unless:blocks.*.type,image', 'required', 'string', 'max:80'],
            'blocks.*.data.caption' => ['exclude_unless:blocks.*.type,image', 'nullable', 'string', 'max:200', new PlainText],
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
