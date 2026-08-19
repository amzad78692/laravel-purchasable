<?php

namespace Amzad\LaravelPurchasable\Exceptions;

use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Models\OrderItem;

class RefundExceedsPaidAmountException extends PurchasableException
{
    public static function for(Order $order, string $alreadyRefunded, string $attempted): self
    {
        return new self(__("Refund of [:attempted] would exceed order [:id]'s paid amount [:paid] (already refunded [:refunded]).", [
            'attempted' => $attempted,
            'id' => $order->getKey(),
            'paid' => $order->amount,
            'refunded' => $alreadyRefunded,
        ]));
    }

    public static function forItem(OrderItem $item, string $alreadyRefunded, string $attempted): self
    {
        return new self(__("Refund of [:attempted] would exceed order item [:id]'s amount [:amount] (already refunded [:refunded]).", [
            'attempted' => $attempted,
            'id' => $item->getKey(),
            'amount' => $item->amount,
            'refunded' => $alreadyRefunded,
        ]));
    }
}
