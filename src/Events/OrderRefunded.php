<?php

namespace Amzad\LaravelPurchasable\Events;

use Amzad\LaravelPurchasable\Models\Order;

class OrderRefunded
{
    public function __construct(public readonly Order $order) {}
}
