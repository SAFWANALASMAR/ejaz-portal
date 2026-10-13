<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * مزود البث (requirements/09-extras.md): يوتيوب يُدمج في الصفحة، وتيمز يُفتح برابط الانضمام لأنه
 * لا يُدمج في إطار.
 */
enum LiveStreamProvider: string implements HasLabel
{
    case Youtube = 'youtube';
    case Teams = 'teams';

    public function getLabel(): string
    {
        return __('livestream.providers.'.$this->value);
    }
}
