<?php

namespace Amzad\LaravelPurchasable\States\Transitions;

use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\CancelledState;
use Spatie\ModelStates\Transition;

class PendingToCancelled extends Transition
{
    public function __construct(protected Order $order) {}

    public function handle(): Order
    {
        $this->order->status = new CancelledState($this->order);
        $this->order->save();

        return $this->order;
    }
}
