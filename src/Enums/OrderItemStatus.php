<?php

namespace Amzad\LaravelPurchasable\Enums;

enum OrderItemStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
