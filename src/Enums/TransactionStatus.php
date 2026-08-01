<?php

namespace Amzad\LaravelPurchasable\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Success => __('Success'),
            self::Failed => __('Failed'),
        };
    }
}
