<?php

namespace Amzad\LaravelPurchasable\States\Transitions;

use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\PartiallyRefundedState;
use Spatie\ModelStates\Transition;

class PaidToPartiallyRefunded extends Transition
{
    public function __construct(protected Order $order) {}

    public function handle(): Order
    {
        $this->order->status = new PartiallyRefundedState($this->order);
        $this->order->save();

        return $this->order;
    }
}
