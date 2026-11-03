<?php

namespace App\Http\Requests;

use App\Actions\Library\RecordLibraryProgress;
use App\Models\LibraryItem;
use App\Models\LibraryProgress;
use App\Support\LibraryPageCount;
use App\Support\LibraryProgramProgress;
use App\Support\PageSet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * بلاغ القارئ أو المشغّل عن تقدّم صاحب الحساب في عنصر منشور (docs/decisions/2026-11-01-my-reading.md):
 * - الصفحات (دفعة حتى 50) ورقم آخر صفحة: للكتاب في قارئ الصفحة وحده، ولا تتجاوز عدد صفحاته من ملفه في الخادم.
 * - الثواني منذ البلاغ السابق والموضع والمدة: للمقطع والصوت في مشغّل الصفحة وحده، والثواني حتى دقيقتين في البلاغ. وما زاد
 *   على الوقت الفعلي يقصّه الإجراء تحت قفل الحساب (RecordLibraryProgress)، فلا يضيع البلاغ كله بتأخر طلب سابق.
 * - وبلا حقول: علامة «فتحته» (مقطع يوتيوب، أو كتاب في عارض المتصفح).
 * والعنصر غير المنشور أو في قسم آخر 404 كصفحته.
 */
class RecordLibraryProgressRequest extends FormRequest
{
    private ?LibraryItem $item = null;

    public function item(): LibraryItem
    {
        return $this->item ??= LibraryItem::query()->published()
            ->where('section', (string) $this->route('section'))
            ->where('slug', (string) $this->route('slug'))
            ->firstOrFail();
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', [LibraryProgress::class, $this->item()]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $item = $this->item();

        // وكتاب برنامج «قراءة كتاب» تُحصى صفحاته لمسجّله ولو كان قابلاً للتنزيل (LibraryProgramProgress::readsInProgram)
        if ($item->tracksPages() || LibraryProgramProgress::readsInProgram($this->user(), $item)) {
            $max = LibraryPageCount::for($item) ?? PageSet::MAX_PAGE;

            return [
                'pages' => ['sometimes', 'array', 'list', 'max:'.RecordLibraryProgress::MAX_PAGES_PER_REPORT],
                'pages.*' => ['integer', 'distinct', 'min:1', 'max:'.$max],
                'page' => ['sometimes', 'integer', 'min:1', 'max:'.$max],
                'seconds' => ['prohibited'],
                'position' => ['prohibited'],
                'duration' => ['prohibited'],
            ];
        }

        if ($item->tracksPlayback()) {
            return [
                'pages' => ['prohibited'],
                'page' => ['prohibited'],
                'seconds' => ['sometimes', 'integer', 'min:0', 'max:'.RecordLibraryProgress::MAX_SECONDS_PER_REPORT],
                'duration' => ['sometimes', 'integer', 'min:1', 'max:'.RecordLibraryProgress::MAX_DURATION],
                // لا يتجاوز الموضع مدة المقطع المرسلة معه
                'position' => ['sometimes', 'integer', 'min:0', 'max:'.RecordLibraryProgress::MAX_DURATION, ...($this->has('duration') ? ['lte:duration'] : [])],
            ];
        }

        return array_fill_keys(['pages', 'page', 'seconds', 'position', 'duration'], ['prohibited']);
    }

    /**
     * المدخلات بعد التحقق أعداداً صحيحة.
     *
     * @return array{pages?: list<int>, page?: int, seconds?: int, position?: int, duration?: int}
     */
    public function progress(): array
    {
        $data = $this->validated();
        $progress = [];

        if (isset($data['pages'])) {
            $progress['pages'] = array_values(array_map(intval(...), (array) $data['pages']));
        }

        foreach (['page', 'seconds', 'position', 'duration'] as $key) {
            if (isset($data[$key])) {
                $progress[$key] = (int) $data[$key];
            }
        }

        return $progress;
    }
}
