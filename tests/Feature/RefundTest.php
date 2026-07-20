<?php

use Amzad\LaravelPurchasable\Actions\RecordTransaction;
use Amzad\LaravelPurchasable\Actions\RefundOrder;
use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Amzad\LaravelPurchasable\Exceptions\RefundExceedsPaidAmountException;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\PartiallyRefundedState;
use Amzad\LaravelPurchasable\States\Order\RefundedState;

function payOrder(Order $order): Order
{
    app(RecordTransaction::class)->execute(
        order: $order,
        type: TransactionType::Charge,
        gateway: 'manual',
        amount: $order->amount,
        status: TransactionStatus::Success,
    );

    return $order->refresh();
}

it('fully refunds a paid order and transitions it to refunded', function () {
    $order = payOrder(Order::factory()->pending()->create(['amount' => '20.000', 'currency' => 'KWD']));

    $transaction = app(RefundOrder::class)->execute($order, TransactionStatus::Success);

    expect($transaction->type)->toBe(TransactionType::Refund)
        ->and($transaction->amount)->toBe('20.000');

    $order->refresh();

    expect($order->status->equals(RefundedState::class))->toBeTrue()
        ->and($order->isFullyRefunded())->toBeTrue();
});

it('partially refunds a paid order and moves it to partially_refunded', function () {
    $order = payOrder(Order::factory()->pending()->create(['amount' => '20.000', 'currency' => 'KWD']));

    app(RefundOrder::class)->execute($order, TransactionStatus::Success, amount: '5.000');

    $order->refresh();

    expect($order->status->equals(PartiallyRefundedState::class))->toBeTrue()
        ->and($order->isPartiallyRefunded())->toBeTrue()
        ->and($order->isFullyRefunded())->toBeFalse()
        ->and($order->refundedAmount())->toBe('5.000');
});

it('rejects a refund that would exceed the paid amount', function () {
    $order = payOrder(Order::factory()->pending()->create(['amount' => '20.000', 'currency' => 'KWD']));

    app(RefundOrder::class)->execute($order, TransactionStatus::Success, amount: '15.000');

    expect(fn () => app(RefundOrder::class)->execute($order, TransactionStatus::Success, amount: '10.000'))
        ->toThrow(RefundExceedsPaidAmountException::class);
});

it('fully refunds across two partial refunds', function () {
    $order = payOrder(Order::factory()->pending()->create(['amount' => '20.000', 'currency' => 'KWD']));

    app(RefundOrder::class)->execute($order, TransactionStatus::Success, amount: '12.000');
    app(RefundOrder::class)->execute($order, TransactionStatus::Success, amount: '8.000');

    $order->refresh();

    expect($order->status->equals(RefundedState::class))->toBeTrue();
});

it('records a failed refund attempt without changing the order status', function () {
    $order = payOrder(Order::factory()->pending()->create(['amount' => '20.000', 'currency' => 'KWD']));

    $transaction = app(RefundOrder::class)->execute($order, TransactionStatus::Failed);

    expect($transaction->status)->toBe(TransactionStatus::Failed);

    $order->refresh();

    expect($order->isPaid())->toBeTrue()
        ->and($order->refundedAmount())->toBe('0.000');
});
