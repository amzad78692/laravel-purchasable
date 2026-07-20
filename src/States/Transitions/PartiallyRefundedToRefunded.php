<?php

namespace Amzad\LaravelPurchasable\States\Transitions;

use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\RefundedState;
use Spatie\ModelStates\Transition;

class PartiallyRefundedToRefunded extends Transition
{
    public function __construct(protected Order $order) {}

    public function handle(): Order
    {
        $this->order->status = new RefundedState($this->order);
        $this->order->save();

        return $this->order;
    }
}
