<?php

namespace Amzad\LaravelPurchasable\Exceptions;

use Amzad\LaravelPurchasable\Models\Order;

class OrderTrashedException extends PurchasableException
{
    public static function for(Order $order): self
    {
        return new self(__('Order [:id] has been deleted and cannot be transitioned.', ['id' => $order->getKey()]));
    }
}
