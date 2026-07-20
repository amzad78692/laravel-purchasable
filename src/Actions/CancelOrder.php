<?php

namespace Amzad\LaravelPurchasable\Actions;

use Amzad\LaravelPurchasable\Events\OrderCancelled;
use Amzad\LaravelPurchasable\Models\Order;

class CancelOrder
{
    public function execute(Order $order): Order
    {
        $order = $order->cancel();

        event(new OrderCancelled($order));

        return $order;
    }
}
