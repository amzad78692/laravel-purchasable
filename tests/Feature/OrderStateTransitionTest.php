<?php

use Amzad\LaravelPurchasable\Exceptions\InvalidTransitionException;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\CancelledState;
use Amzad\LaravelPurchasable\States\Order\FailedState;
use Amzad\LaravelPurchasable\States\Order\PaidState;
use Amzad\LaravelPurchasable\States\Order\PartiallyRefundedState;
use Amzad\LaravelPurchasable\States\Order\PendingState;
use Amzad\LaravelPurchasable\States\Order\RefundedState;

it('defaults a new order to the pending state', function () {
    $order = Order::factory()->create();

    expect($order->status->equals(PendingState::class))->toBeTrue();
});

it('transitions pending to paid', function () {
    $order = Order::factory()->pending()->create();

    $order = $order->markAsPaid();

    expect($order->status->equals(PaidState::class))->toBeTrue()
        ->and($order->paid_at)->not->toBeNull();
});

it('transitions pending to failed', function () {
    $order = Order::factory()->pending()->create();

    $order = $order->markAsFailed();

    expect($order->status->equals(FailedState::class))->toBeTrue();
});

it('transitions pending to cancelled', function () {
    $order = Order::factory()->pending()->create();

    $order = $order->cancel();

    expect($order->status->equals(CancelledState::class))->toBeTrue();
});

it('transitions failed to cancelled', function () {
    $order = Order::factory()->failed()->create();

    $order = $order->cancel();

    expect($order->status->equals(CancelledState::class))->toBeTrue();
});

it('transitions failed back to pending (retry)', function () {
    $order = Order::factory()->failed()->create();

    $order = $order->retry();

    expect($order->status->equals(PendingState::class))->toBeTrue();
});

it('transitions paid to refunded', function () {
    $order = Order::factory()->paid()->create();

    $order = $order->markAsRefunded();

    expect($order->status->equals(RefundedState::class))->toBeTrue();
});

it('transitions paid to partially refunded, then to refunded', function () {
    $order = Order::factory()->paid()->create();

    $order = $order->markAsPartiallyRefunded();
    expect($order->status->equals(PartiallyRefundedState::class))->toBeTrue();

    $order = $order->markAsRefunded();
    expect($order->status->equals(RefundedState::class))->toBeTrue();
});

it('rejects disallowed transitions', function (string $from, string $to) {
    $order = Order::factory()->create(['status' => $from]);

    expect(fn () => $order->transitionStatusTo($to))
        ->toThrow(InvalidTransitionException::class);
})->with([
    'pending -> refunded' => [PendingState::class, RefundedState::class],
    'pending -> partially_refunded' => [PendingState::class, PartiallyRefundedState::class],
    'paid -> pending' => [PaidState::class, PendingState::class],
    'paid -> failed' => [PaidState::class, FailedState::class],
    'paid -> cancelled' => [PaidState::class, CancelledState::class],
    'failed -> paid' => [FailedState::class, PaidState::class],
    'failed -> refunded' => [FailedState::class, RefundedState::class],
    'failed -> partially_refunded' => [FailedState::class, PartiallyRefundedState::class],
    'cancelled -> pending' => [CancelledState::class, PendingState::class],
    'cancelled -> paid' => [CancelledState::class, PaidState::class],
    'cancelled -> failed' => [CancelledState::class, FailedState::class],
    'cancelled -> refunded' => [CancelledState::class, RefundedState::class],
    'cancelled -> partially_refunded' => [CancelledState::class, PartiallyRefundedState::class],
    'refunded -> pending' => [RefundedState::class, PendingState::class],
    'refunded -> paid' => [RefundedState::class, PaidState::class],
    'refunded -> failed' => [RefundedState::class, FailedState::class],
    'refunded -> cancelled' => [RefundedState::class, CancelledState::class],
    'refunded -> partially_refunded' => [RefundedState::class, PartiallyRefundedState::class],
    'partially_refunded -> pending' => [PartiallyRefundedState::class, PendingState::class],
    'partially_refunded -> paid' => [PartiallyRefundedState::class, PaidState::class],
    'partially_refunded -> failed' => [PartiallyRefundedState::class, FailedState::class],
    'partially_refunded -> cancelled' => [PartiallyRefundedState::class, CancelledState::class],
]);
