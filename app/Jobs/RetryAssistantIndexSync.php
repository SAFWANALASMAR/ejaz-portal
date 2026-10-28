<?php

namespace App\Jobs;

use App\Support\Assistant\AssistantIndex;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Foundation\Queue\Queueable;

/**
 * إعادة مزامنة سجل واحد في فهرس المساعد بعد تعذّرها عند الحفظ (AssistantIndex::sync). تقرأ السجل عند التنفيذ، ومنه
 * المحذوف حذفاً ناعماً، فتطابق آخر حاله: منشور فمقاطعه، وإلا فلا مقاطع. ومحاولاتها متباعدة، وإعادة البناء الليلية بعدها.
 */
class RetryAssistantIndexSync implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    /**
     * @param  class-string<Model>  $class
     */
    public function __construct(public string $class, public int|string $id) {}

    public function handle(): void
    {
        $source = AssistantIndex::MODELS[$this->class] ?? null;

        if ($source === null) {
            return;
        }

        $query = $this->class::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($this->class), true)) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        AssistantIndex::refresh($source, $query->find($this->id), $this->id);
    }
}
