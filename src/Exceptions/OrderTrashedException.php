<?php

namespace Amzad\LaravelPurchasable\Exceptions;

use Amzad\LaravelPurchasable\Models\Order;

class OrderTrashedException extends PurchasableException
{
    public static function for(Order $order): self
    {
        return new self("Order [{$order->getKey()}] has been deleted and cannot be transitioned.");
    }
}
