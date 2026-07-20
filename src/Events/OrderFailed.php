<?php

namespace Amzad\LaravelPurchasable\Events;

use Amzad\LaravelPurchasable\Models\Order;

class OrderFailed
{
    public function __construct(public readonly Order $order) {}
}
