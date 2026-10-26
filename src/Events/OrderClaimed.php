<?php

namespace Amzad\LaravelPurchasable\Events;

use Amzad\LaravelPurchasable\Models\Order;

/**
 * A guest order was attached to a buyer model via Buyer::claimGuestOrders().
 */
class OrderClaimed
{
    public function __construct(public readonly Order $order) {}
}
