<?php

namespace Amzad\LaravelPurchasable\Enums;

enum TransactionType: string
{
    case Charge = 'charge';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Charge => __('Charge'),
            self::Refund => __('Refund'),
        };
    }
}
