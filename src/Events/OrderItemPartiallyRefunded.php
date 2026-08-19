<?php

namespace Amzad\LaravelPurchasable\Events;

use Amzad\LaravelPurchasable\Models\OrderItem;

class OrderItemPartiallyRefunded
{
    public function __construct(public readonly OrderItem $item) {}
}
