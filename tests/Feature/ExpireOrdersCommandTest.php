<?php

use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\CancelledState;
use Amzad\LaravelPurchasable\States\Order\PaidState;
use Amzad\LaravelPurchasable\States\Order\PendingState;

it('does nothing when pending_order_ttl is not configured', function () {
    config()->set('purchasable.pending_order_ttl', null);

    $order = Order::factory()->pending()->create(['created_at' => now()->subDays(2)]);

    $this->artisan('purchasable:expire-orders')->assertSuccessful();

    expect($order->refresh()->status->equals(PendingState::class))->toBeTrue();
});

it('cancels pending orders older than the configured ttl', function () {
    config()->set('purchasable.pending_order_ttl', 30);

    $stale = Order::factory()->pending()->create(['created_at' => now()->subMinutes(45)]);
    $fresh = Order::factory()->pending()->create(['created_at' => now()->subMinutes(5)]);

    $this->artisan('purchasable:expire-orders')->assertSuccessful();

    expect($stale->refresh()->status->equals(CancelledState::class))->toBeTrue()
        ->and($fresh->refresh()->status->equals(PendingState::class))->toBeTrue();
});

it('leaves non-pending orders alone regardless of age', function () {
    config()->set('purchasable.pending_order_ttl', 30);

    $paid = Order::factory()->paid()->create(['created_at' => now()->subDays(1)]);

    $this->artisan('purchasable:expire-orders')->assertSuccessful();

    expect($paid->refresh()->status->equals(PaidState::class))->toBeTrue();
});
