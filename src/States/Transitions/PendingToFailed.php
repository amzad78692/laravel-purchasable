<?php

namespace Amzad\LaravelPurchasable\States\Transitions;

use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\FailedState;
use Spatie\ModelStates\Transition;

class PendingToFailed extends Transition
{
    public function __construct(protected Order $order) {}

    public function handle(): Order
    {
        $this->order->status = new FailedState($this->order);
        $this->order->save();

        return $this->order;
    }
}
