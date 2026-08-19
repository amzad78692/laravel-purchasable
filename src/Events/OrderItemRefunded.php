<?php

namespace Amzad\LaravelPurchasable\Events;

use Amzad\LaravelPurchasable\Models\OrderItem;

class OrderItemRefunded
{
    public function __construct(public readonly OrderItem $item) {}
}
