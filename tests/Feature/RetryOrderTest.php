<?php

use Amzad\LaravelPurchasable\Actions\RetryOrder;
use Amzad\LaravelPurchasable\Events\OrderRetried;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\PendingState;
use Illuminate\Support\Facades\Event;

it('moves a failed order back to pending and fires OrderRetried', function () {
    Event::fake([OrderRetried::class]);

    $order = Order::factory()->failed()->create();

    $retried = app(RetryOrder::class)->execute($order);

    expect($retried->status->equals(PendingState::class))->toBeTrue();
    Event::assertDispatchedTimes(OrderRetried::class, 1);
});
