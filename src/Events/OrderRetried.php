<?php

namespace Amzad\LaravelPurchasable\Events;

use Amzad\LaravelPurchasable\Models\Order;

class OrderRetried
{
    public function __construct(public readonly Order $order) {}
}
