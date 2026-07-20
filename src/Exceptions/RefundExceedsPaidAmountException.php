<?php

namespace Amzad\LaravelPurchasable\Exceptions;

use Amzad\LaravelPurchasable\Models\Order;

class RefundExceedsPaidAmountException extends PurchasableException
{
    public static function for(Order $order, string $alreadyRefunded, string $attempted): self
    {
        return new self(sprintf(
            "Refund of [%s] would exceed order [%s]'s paid amount [%s] (already refunded [%s]).",
            $attempted,
            $order->getKey(),
            $order->amount,
            $alreadyRefunded,
        ));
    }
}
