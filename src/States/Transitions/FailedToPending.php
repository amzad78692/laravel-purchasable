<?php

namespace Amzad\LaravelPurchasable\States\Transitions;

use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\PendingState;
use Spatie\ModelStates\Transition;

class FailedToPending extends Transition
{
    public function __construct(protected Order $order) {}

    public function handle(): Order
    {
        $this->order->status = new PendingState($this->order);
        $this->order->save();

        return $this->order;
    }
}
