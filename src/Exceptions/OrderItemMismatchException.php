<?php

namespace Amzad\LaravelPurchasable\Exceptions;

use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Models\OrderItem;

class OrderItemMismatchException extends PurchasableException
{
    public static function for(Order $order, OrderItem $item): self
    {
        return new self(__('Order item [:item] does not belong to order [:order].', [
            'item' => $item->getKey(),
            'order' => $order->getKey(),
        ]));
    }
}
