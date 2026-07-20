<?php

namespace Amzad\LaravelPurchasable\Exceptions;

use Amzad\LaravelPurchasable\Models\Order;

class CurrencyMismatchException extends PurchasableException
{
    public static function for(Order $order, string $givenCurrency): self
    {
        return new self("Transaction currency [{$givenCurrency}] does not match order currency [{$order->currency}].");
    }
}
