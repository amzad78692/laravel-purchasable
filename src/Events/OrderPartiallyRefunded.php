<?php

namespace Amzad\LaravelPurchasable\Events;

use Amzad\LaravelPurchasable\Models\Order;

class OrderPartiallyRefunded
{
    public function __construct(public readonly Order $order) {}
}
