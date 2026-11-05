<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * تصنيف عنصر «المتجر والتبرعات»: تبرع (سهم أو مشروع يُدفع مبلغه في متجر سلة)، أو إصدار (كتاب أو منتج يُشترى منه).
 * ويحدد تبويبه في الصفحة ونص زره.
 */
enum StoreItemCategory: string implements HasLabel
{
    case Donation = 'donation';
    case Publication = 'publication';

    public function getLabel(): string
    {
        return __('store.categories.'.$this->value);
    }

    /**
     * نص زر البطاقة: «تبرّع» للتبرع، و«اشترِ» للإصدار.
     */
    public function action(): string
    {
        return __('store.page.actions.'.$this->value);
    }
}
