<?php

namespace Amzad\LaravelPurchasable\Events;

use Amzad\LaravelPurchasable\Models\Order;

class OrderPaid
{
    public function __construct(public readonly Order $order) {}
}
