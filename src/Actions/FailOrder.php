<?php

namespace Amzad\LaravelPurchasable\Actions;

use Amzad\LaravelPurchasable\Events\OrderFailed;
use Amzad\LaravelPurchasable\Models\Order;

/**
 * Explicitly gives up on a pending order (e.g. after exhausting retries).
 * RecordTransaction never calls this on its own — a single failed charge
 * is expected and doesn't move the order out of Pending; only the host
 * knows when it's done retrying.
 */
class FailOrder
{
    public function execute(Order $order): Order
    {
        $order = $order->markAsFailed();

        event(new OrderFailed($order));

        return $order;
    }
}
