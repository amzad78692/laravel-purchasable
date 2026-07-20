<?php

namespace Amzad\LaravelPurchasable\States\Transitions;

use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\PaidState;
use Spatie\ModelStates\Transition;

class PendingToPaid extends Transition
{
    public function __construct(protected Order $order) {}

    public function handle(): Order
    {
        $this->order->status = new PaidState($this->order);
        $this->order->paid_at ??= now();
        $this->order->save();

        return $this->order;
    }
}
