<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * أنواع عناصر الأرشيف الشامل (requirements/05): الوثائق، والمراسلات الواردة والصادرة برقم قيد وتاريخ،
 * والشواهد التي توثق تنفيذ البرامج.
 */
enum ArchiveItemType: string implements HasLabel
{
    case Document = 'document';
    case Incoming = 'incoming';
    case Outgoing = 'outgoing';
    case Evidence = 'evidence';

    public function getLabel(): string
    {
        return __('documents.archive_types.'.$this->value);
    }

    /**
     * المراسلات وحدها برقم قيد تسلسلي في كل سنة.
     */
    public function numbered(): bool
    {
        return $this === self::Incoming || $this === self::Outgoing;
    }
}
