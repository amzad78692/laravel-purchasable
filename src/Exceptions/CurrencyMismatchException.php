<?php

namespace Amzad\LaravelPurchasable\Exceptions;

use Amzad\LaravelPurchasable\Models\Order;

class CurrencyMismatchException extends PurchasableException
{
    public static function for(Order $order, string $givenCurrency): self
    {
        return new self(__('Transaction currency [:given] does not match order currency [:expected].', [
            'given' => $givenCurrency,
            'expected' => $order->currency,
        ]));
    }
}
