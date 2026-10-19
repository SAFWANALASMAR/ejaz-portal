<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AssistantRating: string implements HasLabel
{
    case Helpful = 'helpful';
    case Unhelpful = 'unhelpful';

    public function getLabel(): string
    {
        return __('assistant.ratings.'.$this->value);
    }
}
