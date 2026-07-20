<?php

namespace Amzad\LaravelPurchasable\Actions;

use Amzad\LaravelPurchasable\Events\OrderRetried;
use Amzad\LaravelPurchasable\Models\Order;

/**
 * Moves a Failed order back to Pending so a host can attempt payment again
 * on the same order, as an alternative to allow_multiple_pending_orders.
 */
class RetryOrder
{
    public function execute(Order $order): Order
    {
        $order = $order->retry();

        event(new OrderRetried($order));

        return $order;
    }
}
